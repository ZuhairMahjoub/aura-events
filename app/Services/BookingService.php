<?php

namespace App\Services;

use App\Contracts\BookingStrategyInterface;
use App\DTOs\BookingData;
use App\Events\BookingAccepted;
use App\Models\Booking;
use App\Models\Listing;
use App\Services\Booking\BookingStrategyFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\BookingCreated;
use Illuminate\Validation\ValidationException;
use App\Events\BookingCancelled;
use Exception;

class BookingService
{
    public function __construct(
        private readonly BookingStrategyFactory $strategyFactory,
    ) {}

    /**
     * نقطة الدخول الوحيدة لإنشاء أي حجز بغض النظر عن نوعه.
     */
    public function book(BookingData $data): Booking
    {
        // إصلاح 3.10: تحميل variants مباشرةً لتجنب استعلام إضافي في calculatePrice
        $listing = Listing::with(['provider', 'variants'])->findOrFail($data->listingId);

        $strategy = $this->strategyFactory->make($listing->listing_type);

        return DB::transaction(function () use ($data, $listing, $strategy) {

            $strategy->validate($data);

            $strategy->reserveCapacity($data);

            $timeSnapshot = $strategy->buildTimeSnapshot($data);
            $typeMetadata = $strategy->buildTypeMetadata($data);

            $mergedMetadata = array_merge($data->metadata, $typeMetadata);

            $booking = Booking::create([
                'user_id'             => $data->userId,
                'provider_id'         => $listing->provider_id,
                'listing_id'          => $data->listingId,
                'listing_variant_id'  => $data->variantId,
                'listing_slot_id'     => $data->slotId,
                'booking_type'        => $listing->listing_type,
                'status'              => 'pending',
                'payment_status'      => 'unpaid',
                'quantity'            => $data->quantity,
                'total_price'         => $this->calculatePrice($data, $listing),
                'currency'            => 'SYP',
                'booked_date'         => $timeSnapshot['booked_date'],
                'booked_start_time'   => $timeSnapshot['booked_start_time'],
                'booked_end_time'     => $timeSnapshot['booked_end_time'],
                'metadata'            => $mergedMetadata,
                'customer_notes'      => $data->customerNotes,
            ]);

            DB::afterCommit(function () use ($booking) {
                event(new BookingCreated($booking));
            });

            Log::info('Booking created successfully', [
                'booking_id'   => $booking->id,
                'listing_type' => $booking->booking_type,
                'user_id'      => $booking->user_id,
            ]);

            return $booking->load([
                'listing:id,title',
                'listing.variants:id,listing_id,variant_name',
            ]);
        });
    }

    public function cancel(string $bookingId, string $cancelledBy, ?string $reason = null): Booking
    {
        return DB::transaction(function () use ($bookingId, $cancelledBy, $reason) {

            $booking = Booking::lockForUpdate()->findOrFail($bookingId);

            $this->assertCanBeCancelled($booking, $cancelledBy);

            $this->releaseCapacity($booking);

            $booking->update([
                'status'               => 'cancelled',
                'cancelled_at'         => now(),
                'cancelled_by'         => $cancelledBy,  // يجب أن يكون 'organizer'|'provider'|'admin'|'system'
                'cancellation_reason'  => $reason,
            ]);

            DB::afterCommit(fn() => event(new \App\Events\BookingCancelled($booking)));

            return $booking->fresh();
        });
    }

    /**
     * إصلاح 3.4: إضافة lockForUpdate وإطلاق الطاقة الاستيعابية عند الرفض.
     * إصلاح 3.7: استخدام قيمة enum صحيحة ('provider').
     */
    public function reject(string $bookingId, string $providerId, ?string $reason): Booking
    {
        return DB::transaction(function () use ($bookingId, $providerId, $reason) {

            // ✅ lockForUpdate لمنع Race Condition مع cancel() أو accept()
            $booking = Booking::lockForUpdate()->findOrFail($bookingId);

            if ($booking->provider_id !== $providerId) {
                throw new \DomainException('هذا الحجز لا يخص مزود الخدمة الحالي.', 403);
            }

            if ($booking->status !== 'pending') {
                throw new \DomainException('لا يمكن رفض حجز تم معالجته مسبقاً.', 400);
            }

            // إطلاق الطاقة الاستيعابية عند الرفض (مثل cancel)
            $this->releaseCapacity($booking);

            $booking->update([
                'status'              => 'rejected',
                'cancelled_by'        => 'provider',   // ✅ قيمة صحيحة في الـ enum
                'cancellation_reason' => $reason,
                'cancelled_at'        => now(),
            ]);

            DB::afterCommit(fn() => event(new \App\Events\BookingCancelled($booking)));

            return $booking->fresh();
        });
    }

    /**
     * إصلاح 3.10: استخدام الـ variants المحملة مسبقاً بدلاً من استعلام جديد.
     */
    private function calculatePrice(BookingData $data, Listing $listing): float
    {
        // listing->variants محملة مسبقاً في book() بـ Eager Loading
        $variant = $listing->variants->firstWhere('id', $data->variantId);

        if (!$variant) {
            throw new \DomainException("الـ Variant المطلوب لا ينتمي لهذا الـ Listing.");
        }

        return (float) $variant->price * $data->quantity;
    }

    private function releaseCapacity(Booking $booking): void
    {
        match ($booking->booking_type) {
            'physical_product' => $this->releasePhysicalProductCapacity($booking),
            'hall'             => $this->releaseSlotCapacity($booking),
            'service'          => $this->releaseSlotCapacity($booking),
            default            => null,
        };
    }

    private function releasePhysicalProductCapacity(Booking $booking): void
    {
        \App\Models\ListingVariant::lockForUpdate()
            ->find($booking->listing_variant_id)
            ?->increment('stock_quantity', $booking->quantity);

        if ($booking->listing_slot_id) {
            \App\Models\ListingSlot::lockForUpdate()
                ->find($booking->listing_slot_id)
                ?->increment('remaining_capacity', $booking->quantity);
        }
    }

    private function releaseSlotCapacity(Booking $booking): void
    {
        if ($booking->listing_slot_id) {
            \App\Models\ListingSlot::lockForUpdate()
                ->find($booking->listing_slot_id)
                ?->increment('remaining_capacity', $booking->quantity);
        }
    }

    private function assertCanBeCancelled(Booking $booking, string $cancelledBy): void
    {
        $nonCancellableStatuses = ['completed', 'cancelled', 'rejected'];

        if (in_array($booking->status, $nonCancellableStatuses)) {
            throw new \DomainException(
                "لا يمكن إلغاء حجز بحالة: [{$booking->status}]"
            );
        }

        if ($cancelledBy !== 'organizer') {
            return;
        }

        $booking->loadMissing('listing');
        $listing = $booking->listing;

        if ($booking->status === 'pending' && !$listing->cancel_before_acceptance) {
            throw new \DomainException(
                "سياسة هذا الإعلان لا تسمح للعميل بإلغاء الطلب وهو في مرحلة الانتظار."
            );
        }

        if (in_array($booking->status, ['accepted', 'confirmed']) && !$listing->cancel_after_acceptance) {
            throw new \DomainException(
                "عذراً، لا يمكن إلغاء الحجز بعد موافقة مزود الخدمة بناءً على سياسة الإعلان."
            );
        }

        if ($booking->payment_status === 'paid' && $listing->cancel_before_payment) {
            throw new \DomainException(
                "لا يمكن إلغاء الحجز بعد إتمام عملية الدفع بناءً على شروط الإعلان."
            );
        }
    }

    public function getUserBookings(string $userId, array $filters = [], int $perPage = 15)
    {
        return Booking::query()
            ->where('user_id', $userId)
            ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))
            ->when($filters['booking_type'] ?? null, fn($q, $type) => $q->where('booking_type', $type))
            ->with([
                'listing:id,title,listing_type',
                'variant:id,variant_name,price,currency',
                'slot:id,slot_name,start_time,end_time',
                'provider:id,brand_name',
            ])
            ->latest()
            ->paginate($perPage);
    }

    public function getProviderBookings(string $providerId, array $filters = [], int $perPage = 15)
    {
        return Booking::query()
            ->where('provider_id', $providerId)
            ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))
            ->when($filters['booking_type'] ?? null, fn($q, $type) => $q->where('booking_type', $type))
            ->with([
                'user:id,first_name,last_name,phone,email',
                'listing:id,title,listing_type',
                'variant:id,variant_name,price,currency',
                'slot:id,slot_name,start_time,end_time',
            ])
            ->latest()
            ->paginate($perPage);
    }

    public function accept(string $bookingId, string $providerId): Booking
    {
        return DB::transaction(function () use ($bookingId, $providerId) {

            $booking = Booking::lockForUpdate()->findOrFail($bookingId);

            if ($booking->provider_id !== $providerId) {
                throw new \DomainException('لا تملك صلاحية التعامل مع هذا الحجز.');
            }

            if ($booking->status !== 'pending') {
                throw new \DomainException(
                    "لا يمكن قبول حجز بحالة [{$booking->status}]، يجب أن يكون [pending]."
                );
            }

            $booking->update(['status' => 'accepted']);

            DB::afterCommit(fn() => event(new BookingAccepted($booking)));

            return $booking->fresh();
        });
    }

    public function complete(string $bookingId): Booking
    {
        return DB::transaction(function () use ($bookingId) {
            $booking = Booking::lockForUpdate()->findOrFail($bookingId);

            if ($booking->status !== 'accepted') {
                throw new Exception('يمكن فقط إنهاء الحجوزات المقبولة مسبقاً.', 400);
            }

            $booking->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            return $booking->fresh();
        });
    }
}

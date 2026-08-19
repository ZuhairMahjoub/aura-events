<?php

namespace App\Services;

use App\Contracts\BookingStrategyInterface;
use App\DTOs\BookingData;
use App\Events\BookingAccepted;
use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Models\FreelancerBlockedDate;
use App\Models\Listing;
use App\Models\ListingSlot;
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

    private const VALID_CANCELLERS = ['organizer', 'provider', 'admin', 'system'];

    public function book(BookingData $data): Booking
    {
        $listing = Listing::with(['provider', 'variants'])->findOrFail($data->listingId);

        if ($listing->moderation_status !== 'approved') {
            throw ValidationException::withMessages([
                'listing_id' => 'هذا الإعلان غير متاح للحجز حالياً.',
            ]);
        }

        if (! $listing->provider || ! $listing->provider->is_active) {
            throw ValidationException::withMessages([
                'listing_id' => 'مزوّد هذا الإعلان غير نشط حالياً.',
            ]);
        }

        if ($listing->provider->moderation_status !== 'approved') {
            throw ValidationException::withMessages([
                'listing_id' => 'مزوّد هذا الإعلان غير معتمد حالياً.',
            ]);
        }

        if (! $listing->variants->contains('id', $data->variantId)) {
            throw ValidationException::withMessages([
                'listing_variant_id' => 'الـ Variant المحدد لا ينتمي لهذا الـ Listing.',
            ]);
        }

        // 🔍 التحقق الديناميكي الفعلي من السعة المتبقية لمنع أي أخطاء أو قيم سالبة
        if ($data->slotId) {
            $slot = ListingSlot::find($data->slotId);
            
            if ($slot) {
                // حساب السعة المتبقية ديناميكياً بناءً على الحجوزات النشطة الحالية
                $activeBookingsQuantity = $slot->bookings()
                    ->whereNotIn('status', ['cancelled', 'rejected', 'expired'])
                    ->sum('quantity');

                $realRemainingCapacity = max(0, $slot->capacity - $activeBookingsQuantity);

                if ($data->quantity > $realRemainingCapacity) {
                    throw ValidationException::withMessages([
                        'quantity' => 'عذراً، الكمية المطلوبة (' . $data->quantity . ') أكبر من السعة المتبقية الفعلية في هذا الـ Slot (' . $realRemainingCapacity . ').',
                    ]);
                }
            }
        }

        $strategy = $this->strategyFactory->make($listing->listing_type);

        return DB::transaction(function () use ($data, $listing, $strategy) {

            $strategy->validate($data);
            $strategy->reserveCapacity($data);

            $timeSnapshot = $strategy->buildTimeSnapshot($data);
            $typeMetadata = $strategy->buildTypeMetadata($data);
            $mergedMetadata = array_merge($data->metadata, $typeMetadata);

            $booking = Booking::create([
                'user_id'            => $data->userId,
                'provider_id'        => $listing->provider_id,
                'listing_id'         => $data->listingId,
                'listing_variant_id' => $data->variantId,
                'listing_slot_id'    => $data->slotId,
                'booking_type'       => $listing->listing_type,
                'status'             => 'pending',
                'payment_status'     => 'unpaid',
                'quantity'           => $data->quantity,
                'total_price'        => $this->calculatePrice($data, $listing),
                'currency'           => 'SYP',
                'booked_date'        => $timeSnapshot['booked_date'],
                'booked_start_time'  => $timeSnapshot['booked_start_time'],
                'booked_end_time'    => $timeSnapshot['booked_end_time'],
                'metadata'           => $mergedMetadata,
                'customer_notes'     => $data->customerNotes,
                'pending_expires_at' => now()->addHours(config('booking.pending_timeout_hours')),
            ]);

            $this->logTransition($booking, null, 'pending', 'organizer', $data->userId);

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

    public function expireStalePendingBookings(): int
    {
        $staleBookingIds = Booking::where('status', 'pending')
            ->whereNotNull('pending_expires_at')
            ->where('pending_expires_at', '<', now())
            ->pluck('id');

        $expiredCount = 0;

        foreach ($staleBookingIds as $bookingId) {
            DB::transaction(function () use ($bookingId, &$expiredCount) {
                $booking = Booking::lockForUpdate()->find($bookingId);

                if (! $booking || $booking->status !== 'pending') {
                    return;
                }

                $previousStatus = $booking->status;

                $this->releaseCapacity($booking);
                $this->releaseFreelancerDateIfApplicable($booking);

                $paymentStatusUpdate = $booking->payment_status === 'paid'
                    ? ['payment_status' => 'refund_pending']
                    : [];

                $booking->update([
                    'status'              => 'expired',
                    'cancelled_at'        => now(),
                    'cancelled_by'        => 'system',
                    'cancellation_reason' => 'انتهت مهلة الرد من المزوّد (' . config('booking.pending_timeout_hours') . ' ساعة) دون قبول أو رفض.',
                    ...$paymentStatusUpdate,
                ]);

                $this->logTransition($booking, $previousStatus, 'expired', 'system', null, 'انتهاء المهلة تلقائياً');

                DB::afterCommit(fn () => event(new \App\Events\BookingExpired($booking)));

                $expiredCount++;
            });
        }

        return $expiredCount;
    }

    public function cancel(string $bookingId, string $cancelledBy, ?string $reason = null): Booking
    {
        if (! in_array($cancelledBy, self::VALID_CANCELLERS, true)) {
            throw new \InvalidArgumentException("قيمة cancelledBy غير صالحة: [{$cancelledBy}].");
        }

        return DB::transaction(function () use ($bookingId, $cancelledBy, $reason) {

            $booking = Booking::lockForUpdate()->findOrFail($bookingId);
            $previousStatus = $booking->status;

            $this->assertCanBeCancelled($booking, $cancelledBy);
            $this->releaseCapacity($booking);
            $this->releaseFreelancerDateIfApplicable($booking);

            $paymentStatusUpdate = $booking->payment_status === 'paid'
                ? ['payment_status' => 'refund_pending']
                : [];

            $booking->update([
                'status'             => 'cancelled',
                'cancelled_at'       => now(),
                'cancelled_by'       => $cancelledBy,
                'cancellation_reason' => $reason,
                ...$paymentStatusUpdate,
            ]);

            $this->logTransition($booking, $previousStatus, 'cancelled', $cancelledBy, null, $reason);

            DB::afterCommit(fn() => event(new BookingCancelled($booking)));

            return $booking->fresh();
        });
    }

    public function reject(string $bookingId, string $providerId, ?string $reason): Booking
    {
        return DB::transaction(function () use ($bookingId, $providerId, $reason) {

            $booking = Booking::lockForUpdate()->findOrFail($bookingId);

            if ($booking->provider_id !== $providerId) {
                throw new \DomainException('هذا الحجز لا يخص مزود الخدمة الحالي.', 403);
            }

            if ($booking->status !== 'pending') {
                throw new \DomainException('لا يمكن رفض حجز تم معالجته مسبقاً.', 400);
            }

            $previousStatus = $booking->status;
            $this->releaseCapacity($booking);
            $this->releaseFreelancerDateIfApplicable($booking);

            $booking->update([
                'status'              => 'rejected',
                'cancelled_by'        => 'provider',
                'cancellation_reason' => $reason,
                'cancelled_at'        => now(),
            ]);

            $this->logTransition($booking, $previousStatus, 'rejected', 'provider', null, $reason);

            DB::afterCommit(fn() => event(new BookingCancelled($booking)));

            return $booking->fresh();
        });
    }

    public function confirmPayment(string $bookingId, ?string $paymentReference = null): Booking
    {
        return DB::transaction(function () use ($bookingId, $paymentReference) {
            $booking = Booking::lockForUpdate()->findOrFail($bookingId);

            if ($booking->status !== 'accepted') {
                throw new \DomainException(
                    "لا يمكن تأكيد الدفع لحجز بحالة [{$booking->status}]، يجب أن يكون [accepted].",
                    400
                );
            }

            $previousStatus = $booking->status;

            $booking->update([
                'status'            => 'confirmed',
                'payment_status'    => 'paid',
                'payment_reference' => $paymentReference,
            ]);

            $this->logTransition($booking, $previousStatus, 'confirmed', 'system', null, 'Payment confirmed');

            return $booking->fresh();
        });
    }

    public function complete(string $bookingId, string $actorType = 'provider', ?string $actorId = null): Booking
    {
        return DB::transaction(function () use ($bookingId, $actorType, $actorId) {
            $booking = Booking::lockForUpdate()->findOrFail($bookingId);

            if (! in_array($booking->status, ['accepted', 'confirmed'], true)) {
                throw new Exception(
                    "يمكن فقط إنهاء الحجوزات المقبولة أو المؤكَّدة، الحالة الحالية: [{$booking->status}].",
                    400
                );
            }

            if ((float) $booking->total_price > 0 && $booking->payment_status !== 'paid') {
                throw new \DomainException(
                    'لا يمكن إكمال الحجز قبل تأكيد الدفع (payment_status يجب أن تكون paid).',
                    422
                );
            }

            $previousStatus = $booking->status;

            $booking->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            $this->releaseFreelancerDateIfApplicable($booking);

            $this->logTransition($booking, $previousStatus, 'completed', $actorType, $actorId);

            return $booking->fresh();
        });
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

            $previousStatus = $booking->status;
            $booking->update(['status' => 'accepted', 'pending_expires_at' => null]);

            $this->logTransition($booking, $previousStatus, 'accepted', 'provider', null);

            $this->blockFreelancerDateIfApplicable($booking);

            DB::afterCommit(fn() => event(new BookingAccepted($booking)));

            return $booking->fresh();
        });
    }

    private function blockFreelancerDateIfApplicable(Booking $booking): void
    {
        if ($booking->booking_type === 'package') {
            $this->blockPackageFreelancersDate($booking);
            return;
        }

        if ($booking->provider?->provider_type !== 'freelancer') {
            return;
        }

        $startTime = $booking->booked_start_time;
        $endTime   = $booking->booked_end_time;

        if (FreelancerBlockedDate::hasConflict($booking->provider_id, $booking->booked_date, $startTime, $endTime)) {
            throw new \DomainException(
                'هذا الفريلانسر لديه حجز أو حظر متعارض بنفس التاريخ والوقت بالفعل.'
            );
        }

        FreelancerBlockedDate::create([
            'freelancer_id' => $booking->provider_id,
            'blocked_date'  => $booking->booked_date,
            'start_time'    => $startTime,
            'end_time'      => $endTime,
            'source'        => 'booking',
            'booking_id'    => $booking->id,
        ]);
    }

    private function blockPackageFreelancersDate(Booking $booking): void
    {
        $packageFreelancers = $booking->variant()->with('packageFreelancers')->first()?->packageFreelancers ?? collect();

        if ($packageFreelancers->isEmpty()) {
            return;
        }

        $startTime = $booking->booked_start_time;
        $endTime   = $booking->booked_end_time;

        foreach ($packageFreelancers as $packageFreelancer) {
            if (FreelancerBlockedDate::hasConflict($packageFreelancer->freelancer_id, $booking->booked_date, $startTime, $endTime)) {
                throw new \DomainException(
                    'أحد الفريلانسرز المشاركين بهذه الباقة لديه حجز أو حظر متعارض بنفس التاريخ والوقت بالفعل.'
                );
            }
        }

        foreach ($packageFreelancers as $packageFreelancer) {
            FreelancerBlockedDate::create([
                'freelancer_id' => $packageFreelancer->freelancer_id,
                'blocked_date'  => $booking->booked_date,
                'start_time'    => $startTime,
                'end_time'      => $endTime,
                'source'        => 'booking',
                'booking_id'    => $booking->id,
            ]);
        }
    }

    private function releaseFreelancerDateIfApplicable(Booking $booking): void
    {
        FreelancerBlockedDate::where('booking_id', $booking->id)->delete();
    }

    private function logTransition(
        Booking $booking,
        ?string $fromStatus,
        string $toStatus,
        string $actorType,
        ?string $actorId = null,
        ?string $reason = null,
    ): void {
        BookingStatusLog::create([
            'booking_id'  => $booking->id,
            'from_status' => $fromStatus,
            'to_status'   => $toStatus,
            'actor_type'  => $actorType,
            'actor_id'    => $actorId,
            'reason'      => $reason,
            'metadata'    => [
                'payment_status' => $booking->payment_status,
                'total_price'    => $booking->total_price,
            ],
        ]);
    }

    private function calculatePrice(BookingData $data, Listing $listing): float
    {
        $variant = $listing->variants->firstWhere('id', $data->variantId);

        if (!$variant) {
            throw new \DomainException("الـ Variant المطلوب لا ينتمي لهذا الـ Listing.");
        }

        return (float) $variant->price * $data->quantity;
    }

    private function releaseCapacity(Booking $booking): void
    {
        $this->strategyFactory->make($booking->booking_type)->release($booking);
    }

    private function assertCanBeCancelled(Booking $booking, string $cancelledBy): void
    {
        $nonCancellableStatuses = ['completed', 'cancelled', 'rejected'];

        if (in_array($booking->status, $nonCancellableStatuses)) {
            throw new \DomainException("لا يمكن إلغاء حجز بحالة: [{$booking->status}]");
        }

        if ($cancelledBy !== 'organizer') {
            return;
        }

        $booking->loadMissing('listing');
        $listing = $booking->listing;

        if (! $listing) {
            throw new \DomainException(
                'تعذر التحقق من سياسة الإلغاء: الإعلان المرتبط بهذا الحجز لم يعد موجوداً. يرجى التواصل مع الدعم الفني.'
            );
        }

        if ($booking->status === 'pending' && !$listing->cancel_before_acceptance) {
            throw new \DomainException("سياسة هذا الإعلان لا تسمح للعميل بإلغاء الطلب وهو في مرحلة الانتظار.");
        }

        if (in_array($booking->status, ['accepted', 'confirmed']) && !$listing->cancel_after_acceptance) {
            throw new \DomainException("عذراً، لا يمكن إلغاء الحجز بعد موافقة مزود الخدمة بناءً على سياسة الإعلان.");
        }

        if ($booking->payment_status === 'paid' && $listing->cancel_before_payment) {
            throw new \DomainException("لا يمكن إلغاء الحجز بعد إتمام عملية الدفع بناءً على شروط الإعلان.");
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
                'user',
                'slot',
                'listing.images',
                'listing.provider',
                'variant.packageItems.includedVariant',
                'variant.packageFreelancers.freelancer',
            ])
            ->latest()
            ->paginate($perPage);
    }

    public function getStatusHistory(string $bookingId)
    {
        return BookingStatusLog::where('booking_id', $bookingId)
            ->orderBy('created_at')
            ->get();
    }
}
<?php
namespace App\Services;

use App\Contracts\BookingStrategyInterface;
use App\DTOs\BookingData;
use App\Models\Booking;
use App\Models\Listing;
use App\Services\Booking\BookingStrategyFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\BookingCreated;
use Illuminate\Validation\ValidationException;
use App\Events\BookingCancelled;

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
        // جلب الـ Listing للتحقق من النوع وسحب provider_id
        $listing = Listing::with('provider')->findOrFail($data->listingId);

        // حل الـ Strategy المناسبة بناءً على نوع الـ Listing
        $strategy = $this->strategyFactory->make($listing->listing_type);

        return DB::transaction(function () use ($data, $listing, $strategy) {

            // ── المرحلة 1: التحقق من صحة البيانات الخاصة بالنوع ─────────
            // يُطلق Exception → الـ transaction تُلغى تلقائياً
            $strategy->validate($data);

            // ── المرحلة 2: حجز الطاقة مع LOCK (منع Race Conditions) ──────
            // SELECT FOR UPDATE يضمن أن لا transaction أخرى تعدل نفس الصف
            // حتى تنتهي هذه الـ transaction أو تُلغى
            $strategy->reserveCapacity($data);

            // ── المرحلة 3: بناء بيانات السجل ────────────────────────────
            $timeSnapshot = $strategy->buildTimeSnapshot($data);
            $typeMetadata = $strategy->buildTypeMetadata($data);

            // دمج metadata المرسل من العميل مع الـ metadata الداخلية للنوع
            $mergedMetadata = array_merge($data->metadata, $typeMetadata);

            // ── المرحلة 4: إنشاء سجل الحجز ──────────────────────────────
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

            // ── المرحلة 5: إطلاق الأحداث بعد commit ─────────────────────
            // DB::afterCommit يضمن أن الـ Event لا يُطلق إلا بعد نجاح الـ transaction
            // هذا يمنع إرسال إشعار عن حجز ثم يتم rollback
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

    /**
     * إلغاء حجز مع استعادة الطاقة الاستيعابية.
     */
/**
 * إلغاء حجز مع استعادة الطاقة الاستيعابية.
 * يستقبل ID وليس Model جاهز، لضمان قراءة طازجة مع lockForUpdate.
 */
public function cancel(string $bookingId, string $cancelledBy, ?string $reason = null): Booking
{
    return DB::transaction(function () use ($bookingId, $cancelledBy, $reason) {

        // قفل السجل لمنع أي تعديل متزامن (مثل: accept في نفس اللحظة)
        $booking = Booking::lockForUpdate()->findOrFail($bookingId);

        $this->assertCanBeCancelled($booking, $cancelledBy);

        $this->releaseCapacity($booking);

        $booking->update([
            'status'               => 'cancelled',
            'cancelled_at'         => now(),
            'cancelled_by'         => $cancelledBy,
            'cancellation_reason'  => $reason,
        ]);

        DB::afterCommit(fn() => event(new \App\Events\BookingCancelled($booking)));

        return $booking->fresh();
    });
}

    // ── Private Helpers ──────────────────────────────────────────────────────

    private function calculatePrice(BookingData $data, Listing $listing): float
    {
        // سيتضمن منطق السعر: price × quantity، مدة الإيجار، إلخ.
        // في مرحلة MVP: سعر Variant × الكمية
        $variant = $listing->variants()->find($data->variantId);
        return (float) $variant->price * $data->quantity;
    }

    private function releaseCapacity(Booking $booking): void
    {
        match ($booking->booking_type) {
            'physical_product' => $this->releaseStock($booking),
            'hall'             => $this->releaseSlotCapacity($booking),
            'service'          => $this->releaseServiceAvailability($booking),
            default            => null,
        };
    }

    private function releaseStock(Booking $booking): void
    {
        \App\Models\ListingVariant::lockForUpdate()
            ->find($booking->listing_variant_id)
            ?->increment('stock_quantity', $booking->quantity);
    }

    private function releaseSlotCapacity(Booking $booking): void
    {
        if ($booking->listing_slot_id) {
            \App\Models\ListingSlot::lockForUpdate()
                ->find($booking->listing_slot_id)
                ?->increment('remaining_capacity', $booking->quantity);
        }
    }

    private function releaseServiceAvailability(Booking $booking): void
    {
        if (!$booking->listing_slot_id && $booking->booked_date) {
            \App\Models\ListingAvailability::lockForUpdate()
                ->where('listing_variant_id', $booking->listing_variant_id)
                ->where('available_date', $booking->booked_date)
                ->update(['is_blocked' => false]);
        }
    }

    /**
 * يتحقق من إمكانية إلغاء الحجز بناءً على حالته الحالية وسياسات الـ Listing.
 *
 * @throws \DomainException إذا كانت السياسة تمنع الإلغاء
 */
private function assertCanBeCancelled(Booking $booking, string $cancelledBy): void
{
    // 1. الحالات الثابتة التي لا يمكن إلغاؤها تحت أي ظرف
    // أضفنا 'rejected' لأن الحجز المرفوض انتهت دورة حياته فعلياً
    $nonCancellableStatuses = ['completed', 'cancelled', 'rejected'];

    if (in_array($booking->status, $nonCancellableStatuses)) {
        throw new \DomainException(
            "لا يمكن إلغاء حجز بحالة: [{$booking->status}]"
        );
    }

    // 2. المزود والإدارة يتجاوزون كل السياسات أدناه (حق إلغاء/رفض دائم)
    if ($cancelledBy !== 'organizer') {
        return;
    }

    // 3. تأمين تحميل علاقة الـ Listing لقراءة شروط الإلغاء الخاصة به
    $booking->loadMissing('listing');
    $listing = $booking->listing;

    // 4. فحص الإلغاء قبل قبول الطلب (Pending)
    if ($booking->status === 'pending' && !$listing->cancel_before_acceptance) {
        throw new \DomainException(
            "سياسة هذا الإعلان لا تسمح للعميل بإلغاء الطلب وهو في مرحلة الانتظار."
        );
    }

    // 5. فحص الإلغاء بعد قبول الطلب (Accepted / Confirmed)
    if (in_array($booking->status, ['accepted', 'confirmed']) && !$listing->cancel_after_acceptance) {
        throw new \DomainException(
            "عذراً، لا يمكن إلغاء الحجز بعد موافقة مزود الخدمة بناءً على سياسة الإعلان."
        );
    }

    // 6. فحص حالة الدفع — يقفل الإلغاء بعد الدفع إذا كانت السياسة تفرض ذلك
    // ملاحظة: اسم الحقل cancel_before_payment يعني "نافذة الإلغاء تنتهي عند الدفع"
    // وهو معكوس دلالياً عن الحقلين أعلاه — راجع التعليق أسفل الكلاس لمعرفة السبب
    if ($booking->payment_status === 'paid' && $listing->cancel_before_payment) {
        throw new \DomainException(
            "لا يمكن إلغاء الحجز بعد إتمام عملية الدفع بناءً على شروط الإعلان."
        );
    }
}
}
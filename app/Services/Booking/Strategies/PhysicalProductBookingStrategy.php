<?php

namespace App\Services\Booking\Strategies;

use App\Contracts\BookingStrategyInterface;
use App\DTOs\BookingData;
use App\Models\ListingVariant;
use App\Models\ListingSlot;
use App\Models\Booking;
use Illuminate\Validation\ValidationException;
use App\Models\ListingAvailability;

/**
 * استراتيجية حجز المنتجات المادية (Physical Products)
 *
 * تدعم نوعين من الأسعار:
 * 1. Fixed: منتج مادي بسعر ثابت - يتطلب كمية فقط، والخصم من stock_quantity
 *    مباشرة (بيع فعلي، بدون وقت محدد).
 * 2. Hourly: منتج مادي بسعر بالساعة - يتطلب slot زمني محدد. stock_quantity
 *    هنا رقم مرجعي ثابت لا يُخصم أبداً (يمثّل "كم قطعة عند المزوّد إجمالاً"،
 *    مثلاً 1000 كرسي). نفس القطع الفيزيائية مستخدمة بأي وقت من نفس اليوم،
 *    فحجز quantity من أي slot بينخصم من كل الـ slots الأخرى التابعة لنفس
 *    اليوم (listing_availability_id) معاً — لكن أيام مختلفة (availability
 *    مختلفة) تبقى مستقلة تماماً عن بعضها: حجز 1000 كرسي ليوم الاثنين لا
 *    يؤثر إطلاقاً على توفر يوم الجمعة.
 */
class PhysicalProductBookingStrategy implements BookingStrategyInterface
{
    private ?ListingSlot $lockedSlot = null;

    public function validate(BookingData $data): void
    {
        $variant = ListingVariant::findOrFail($data->variantId);

        if ($data->quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'الكمية يجب أن تكون 1 على الأقل.',
            ]);
        }

        if ($variant->price_type === 'hourly') {
            if (empty($data->slotId)) {
                throw ValidationException::withMessages([
                    'listing_slot_id' => 'المنتج بالساعة يتطلب تحديد time slot.',
                ]);
            }

            if (empty($data->bookedDate)) {
                throw ValidationException::withMessages([
                    'booked_date' => 'المنتج بالساعة يتطلب تحديد تاريخ.',
                ]);
            }

            // ملاحظة: فحص التعارض الحقيقي وفحص السعة يبقيان داخل
            // reserveCapacity() تحت lockForUpdate (TOCTOU-safe). هذا الفحص
            // هنا بنيوي فقط (وجود slotId/bookedDate).
        }
    }

    public function reserveCapacity(BookingData $data): void
    {
        $variant = ListingVariant::lockForUpdate()->findOrFail($data->variantId);

        if ($variant->price_type === 'hourly' && !empty($data->slotId)) {
            // نفس القطع الفيزيائية (المخزون) مستخدمة بأي وقت من نفس اليوم،
            // فحجز quantity من أي slot لازم يخصم نفس الكمية من كل الـ slots
            // الأخرى التابعة لنفس اليوم (listing_availability_id) — مو بس
            // الـ slot المحدد بالطلب. stock_quantity يبقى ثابتاً كرقم مرجعي
            // ولا يُخصم منه هنا إطلاقاً.
            //
            // قفل جماعي مرتَّب بـ orderBy('id') لكل sibling slots لمنع
            // Deadlock بين حجزين متزامنين يقفلون نفس مجموعة الـ slots بترتيب
            // معكوس (نفس مبدأ PackageBookingStrategy).
            $requestedSlot = ListingSlot::findOrFail($data->slotId);

            $siblingSlots = ListingSlot::where('listing_availability_id', $requestedSlot->listing_availability_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $insufficientSlot = $siblingSlots->first(
                fn (ListingSlot $slot) => $slot->remaining_capacity < $data->quantity
            );

            if ($insufficientSlot) {
                throw ValidationException::withMessages([
                    'listing_slot_id' => "السعة المتبقية لهذا اليوم غير كافية بسبب فترة [{$insufficientSlot->start_time}-{$insufficientSlot->end_time}]. المتاح: {$insufficientSlot->remaining_capacity}.",
                ]);
            }

            foreach ($siblingSlots as $slot) {
                $slot->decrement('remaining_capacity', $data->quantity);
            }

            $this->lockedSlot = $siblingSlots->firstWhere('id', $requestedSlot->id)?->fresh();

            return;
        }

        // fixed: بيع فعلي بدون slot — الخصم من stock_quantity مباشرة.
        if ($variant->stock_quantity !== null) {
            if ($variant->stock_quantity < $data->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "الكمية المطلوبة ({$data->quantity}) تتجاوز المخزون المتاح ({$variant->stock_quantity}).",
                ]);
            }
            $variant->decrement('stock_quantity', $data->quantity);
        }
    }

    public function buildTimeSnapshot(BookingData $data): array
    {
        $variant = ListingVariant::find($data->variantId);

        if (!$variant) {
            throw ValidationException::withMessages([
                'variant_id' => ['العرض أو المنتج المطلوب غير موجود.']
            ]);
        }

        $requestedDate = $data->bookedDate instanceof \Carbon\Carbon
            ? $data->bookedDate->format('Y-m-d')
            : \Carbon\Carbon::parse($data->bookedDate)->format('Y-m-d');

        if ($variant->price_type === 'hourly' && !empty($data->slotId)) {

            $slot = $this->lockedSlot ?? ListingSlot::with('availability')->find($data->slotId);

            if (!$slot || !$slot->availability) {
                throw ValidationException::withMessages([
                    'slot_id' => ['الفترة الزمنية المطلوبة غير موجودة أو غير متاحة حالياً.']
                ]);
            }

            if ($slot->availability->listing_variant_id !== $variant->id) {
                throw ValidationException::withMessages([
                    'slot_id' => ['الفترة الزمنية المحددة لا تنتمي لهذا العرض.']
                ]);
            }

            if ($slot->availability->is_blocked) {
                throw ValidationException::withMessages([
                    'booked_date' => ['عذراً، هذا اليوم تم إغلاقه من قبل مقدم الخدمة.']
                ]);
            }

            // ملاحظة: فحص remaining_capacity <= 0 محذوف من هنا عمداً — كان
            // يتحقق *بعد* أن ينقصها reserveCapacity() بنفس الحجز الحالي،
            // فيرفض الحجز الذي أنشأه للتو. الفحص الصحيح موجود فقط في
            // reserveCapacity() قبل الـ decrement.

            $availableDate = $slot->availability->available_date instanceof \Carbon\Carbon
                ? $slot->availability->available_date->format('Y-m-d')
                : \Carbon\Carbon::parse($slot->availability->available_date)->format('Y-m-d');

            if ($requestedDate !== $availableDate) {
                throw ValidationException::withMessages([
                    'booked_date' => ['التاريخ المحدد لا يطابق يوم الفترة الزمنية المتاحة في قاعدة البيانات.']
                ]);
            }

            return [
                'booked_date'       => $availableDate,
                'booked_start_time' => $slot->start_time,
                'booked_end_time'   => $slot->end_time,
            ];
        }

        $availability = ListingAvailability::where('listing_variant_id', $variant->id)
            ->where('available_date', $requestedDate)
            ->first();

        if ($availability && $availability->is_blocked) {
            throw ValidationException::withMessages([
                'booked_date' => ['عذراً، هذا اليوم تم إغلاقه من قبل مقدم الخدمة ولا يستقبل حجوزات ثابتة.']
            ]);
        }

        return [
            'booked_date'       => $requestedDate,
            'booked_start_time' => null,
            'booked_end_time'   => null,
        ];
    }

    public function buildTypeMetadata(BookingData $data): array
    {
        $variant = ListingVariant::find($data->variantId);

        return [
            'is_rental'        => $data->metadata['is_rental'] ?? false,
            'rental_days'      => $data->metadata['rental_days'] ?? null,
            'delivery_address' => $data->metadata['delivery_address'] ?? null,
            'price_type'       => $variant?->price_type ?? 'fixed',
        ];
    }

    public function release(Booking $booking): void
    {
        if ($booking->listing_slot_id) {
            // مرآة لـ reserveCapacity: الخصم صار على كل sibling slots بنفس
            // اليوم، فالإرجاع لازم يشمل نفس المجموعة بالضبط، مو الـ slot
            // المحجوز أصلاً فقط. نفس ترتيب القفل (orderBy id) لمنع Deadlock.
            $bookedSlot = ListingSlot::find($booking->listing_slot_id);

            if ($bookedSlot) {
                ListingSlot::where('listing_availability_id', $bookedSlot->listing_availability_id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->each(fn (ListingSlot $slot) => $slot->increment('remaining_capacity', $booking->quantity));
            }

            return;
        }

        // fixed: إرجاع لـ stock_quantity مباشرة (بيع بدون slot).
        $variant = ListingVariant::lockForUpdate()->find($booking->listing_variant_id);

        if ($variant && $variant->stock_quantity !== null) {
            $variant->increment('stock_quantity', $booking->quantity);
        }
    }
}
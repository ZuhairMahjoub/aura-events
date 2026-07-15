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
 * 1. Fixed: منتج مادي بسعر ثابت - يتطلب كمية فقط
 * 2. Hourly: منتج مادي بسعر بالساعة - يتطلب slot زمني محدد
 * 
 * في كلا الحالتين:
 * - يتم التحقق من المخزون (stock_quantity)
 * - يتم تخفيض المخزون عند الحجز
 */
class PhysicalProductBookingStrategy implements BookingStrategyInterface
{
    public function validate(BookingData $data): void
    {
        // جلب المتغير للتحقق من نوع السعر
        $variant = ListingVariant::findOrFail($data->variantId);

        // التحقق من الكمية
        if ($data->quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'الكمية يجب أن تكون 1 على الأقل.',
            ]);
        }

        // إذا كان السعر بالساعة (hourly)، يجب تحديد slot وتاريخ
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

            // التحقق من عدم وجود حجز متعارض
            $overlapping = Booking::where('listing_variant_id', $data->variantId)
                ->where('booked_date', $data->bookedDate)
                ->whereIn('status', ['pending', 'accepted', 'confirmed'])
                ->where(function ($q) use ($data) {
                    $q->whereNotNull('listing_slot_id')
                      ->where('listing_slot_id', $data->slotId);
                })
                ->exists();

            if ($overlapping) {
                throw ValidationException::withMessages([
                    'listing_slot_id' => 'هذا الـ Slot محجوز مسبقاً.',
                ]);
            }
        }
    }

    public function reserveCapacity(BookingData $data): void
    {
        $variant = ListingVariant::lockForUpdate()->findOrFail($data->variantId);

        if ($variant->stock_quantity !== null) {
            if ($variant->stock_quantity < $data->quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "الكمية المطلوبة ({$data->quantity}) تتجاوز المخزون المتاح ({$variant->stock_quantity}).",
                ]);
            }
            $variant->decrement('stock_quantity', $data->quantity);
        }

        if ($variant->price_type === 'hourly' && !empty($data->slotId)) {
            $slot = ListingSlot::lockForUpdate()->findOrFail($data->slotId);

            if ($slot->remaining_capacity < $data->quantity) {
                throw ValidationException::withMessages([
                    'listing_slot_id' => "الطاقة الاستيعابية للـ Slot غير كافية. المتاح: {$slot->remaining_capacity}.",
                ]);
            }

            $slot->decrement('remaining_capacity', $data->quantity);
        }
    }

  public function buildTimeSnapshot(BookingData $data): array
{
    // 1. جلب الـ variant للتحقق من نوع السعر
    $variant = ListingVariant::find($data->variantId);

    if (!$variant) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'variant_id' => ['العرض أو المنتج المطلوب غير موجود.']
        ]);
    }

    // تأمين صيغة التاريخ المرسل مسبقاً لاستخدامه في الحالتين (Fixed أو Hourly)
    $requestedDate = $data->bookedDate instanceof \Carbon\Carbon 
        ? $data->bookedDate->format('Y-m-d') 
        : \Carbon\Carbon::parse($data->bookedDate)->format('Y-m-d');

    // 2. إذا كان السعر بالساعة، نقوم بجلب الـ slot وعمل التحققات الشاملة للوقت والتاريخ
    if ($variant->price_type === 'hourly' && !empty($data->slotId)) {
        
        // جلب الـ slot مع علاقة الـ availability بضربة واحدة لتسريع الأداء
        $slot = $this->lockedSlot ?? ListingSlot::with('availability')->find($data->slotId);

        if (!$slot || !$slot->availability) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'slot_id' => ['الفترة الزمنية المطلوبة غير موجودة أو غير متاحة حالياً.']
            ]);
        }

        // تحقق: هل هذا الـ slot ينتمي فعلاً لنفس المنتج المحدد؟
        if ($slot->availability->listing_variant_id !== $variant->id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'slot_id' => ['الفترة الزمنية المحددة لا تنتمي لهذا العرض.']
            ]);
        }

        // تحقق: هل اليوم مغلق يدوياً من الـ Vendor؟
        if ($slot->availability->is_blocked) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'booked_date' => ['عذراً، هذا اليوم تم إغلاقه من قبل مقدم الخدمة.']
            ]);
        }

        // تحقق: السعة المتبقية للفترة
        if ($slot->remaining_capacity <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'slot_id' => ['عذراً، هذه الفترة ممتلئة بالكامل ولا توجد سعة متبقية.']
            ]);
        }

        // تحقق: مطابقة التاريخ المرسل مع تاريخ الـ availability في قاعدة البيانات
        $availableDate = $slot->availability->available_date instanceof \Carbon\Carbon 
            ? $slot->availability->available_date->format('Y-m-d') 
            : \Carbon\Carbon::parse($slot->availability->available_date)->format('Y-m-d');

        if ($requestedDate !== $availableDate) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'booked_date' => ['التاريخ المحدد لا يطابق يوم الفترة الزمنية المتاحة في قاعدة البيانات.']
            ]);
        }

        // [إصلاح حرج]: إرجاع الأوقات مباشرة بدون ->format() لأنها مخزنة كنصوص "HH:MM:SS"
        return [
            'booked_date'       => $availableDate,
            'booked_start_time' => $slot->start_time, 
            'booked_end_time'   => $slot->end_time,
        ];
    }

    // 3. إذا كان السعر ثابت (Fixed)، نتحقق فقط من إتاحة اليوم وحظر مقدم الخدمة له إن وُجدت الإتاحة
    $availability = ListingAvailability::where('listing_variant_id', $variant->id)
        ->where('available_date', $requestedDate)
        ->first();

    if ($availability && $availability->is_blocked) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'booked_date' => ['عذراً، هذا اليوم تم إغلاقه من قبل مقدم الخدمة ولا يستقبل حجوزات ثابتة.']
        ]);
    }

    // إرجاع أوقات فارغة للسعر الثابت كما كنت تفعل سابقاً
    return [
        'booked_date'       => $requestedDate, 
        'booked_start_time' => null,
        'booked_end_time'   => null,
    ];
}
    public function buildTypeMetadata(BookingData $data): array
    {
        // جلب المتغير للتحقق من نوع السعر
        $variant = ListingVariant::find($data->variantId);

        return [
            'is_rental'        => $data->metadata['is_rental'] ?? false,
            'rental_days'      => $data->metadata['rental_days'] ?? null,
            'delivery_address' => $data->metadata['delivery_address'] ?? null,
            'price_type'       => $variant?->price_type ?? 'fixed', // حفظ نوع السعر للمرجعية
        ];
    }

  
    public function release(Booking $booking): void
    {
        $variant = ListingVariant::lockForUpdate()->find($booking->listing_variant_id);

        if ($variant && $variant->stock_quantity !== null) {
            $variant->increment('stock_quantity', $booking->quantity);
        }

        if ($booking->listing_slot_id) {
            ListingSlot::lockForUpdate()
                ->find($booking->listing_slot_id)
                ?->increment('remaining_capacity', $booking->quantity);
        }
    }
}
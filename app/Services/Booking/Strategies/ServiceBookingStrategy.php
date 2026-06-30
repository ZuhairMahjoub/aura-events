<?php

namespace App\Services\Booking\Strategies;

use App\Contracts\BookingStrategyInterface;
use App\DTOs\BookingData;
use App\Models\ListingSlot;
use App\Models\Booking;
use Illuminate\Validation\ValidationException;

/**
 * استراتيجية حجز الخدمات والصالات (Halls & Freelance Services)
 *
 * تتعامل مع:
 * - الصالات (Halls): تحتاج slot زمني محدد
 * - خدمات الفريلانس (Services): تحتاج slot زمني محدد
 *
 * كلاهما يعتمد على:
 * - listing_slot_id: الوقت المحدد
 * - booked_date: التاريخ
 * - remaining_capacity: الطاقة الاستيعابية للـ slot
 */
class ServiceBookingStrategy implements BookingStrategyInterface
{
    private ?ListingSlot $lockedSlot = null;

    public function validate(BookingData $data): void
    {
        // التحقق من وجود slot محدد
        if (empty($data->slotId)) {
            throw ValidationException::withMessages([
                'listing_slot_id' => 'حجز الخدمة يتطلب تحديد time slot.',
            ]);
        }

        // التحقق من وجود تاريخ محدد
        if (empty($data->bookedDate)) {
            throw ValidationException::withMessages([
                'booked_date' => 'حجز الخدمة يتطلب تحديد تاريخ.',
            ]);
        }

        // إصلاح TOCTOU: فحص التعارض الفعلي نُقل إلى reserveCapacity() ليكون
        // تحت lockForUpdate (راجع HallBookingStrategy لنفس النمط). الفحص
        // هنا كان يحدث *قبل* أي lock، ما يسمح لطلبين متزامنين بتجاوز هذا
        // الفحص معاً، فيتسابقان لاحقاً على remaining_capacity برسالة خطأ
        // مضلِّلة ("الطاقة غير كافية" بدل "محجوز مسبقاً"). validate() هنا
        // للتحقق البنيوي فقط.
    }

    public function reserveCapacity(BookingData $data): void
    {
        // ── Lock هو نقطة التزامن الوحيدة والحقيقية (TOCTOU-safe) ──────────
        $slot = ListingSlot::lockForUpdate()->findOrFail($data->slotId);

        // ── فحص التعارض داخل الـ Lock ────────────────────────────────────
        $overlapping = Booking::where('listing_variant_id', $data->variantId)
            ->where('booked_date', $data->bookedDate)
            ->whereIn('status', ['pending', 'accepted', 'confirmed'])
            ->where('listing_slot_id', $data->slotId)
            ->exists();

        if ($overlapping) {
            throw ValidationException::withMessages([
                'listing_slot_id' => 'هذا الـ Slot محجوز مسبقاً.',
            ]);
        }

        // التحقق من الطاقة الاستيعابية
        if ($slot->remaining_capacity < $data->quantity) {
            throw ValidationException::withMessages([
                'listing_slot_id' => "الطاقة الاستيعابية للـ Slot غير كافية. المتاح: {$slot->remaining_capacity}.",
            ]);
        }

        // تخفيض الطاقة الاستيعابية
        $slot->decrement('remaining_capacity', $data->quantity);

        // حفظ الـ slot المُقفَل لإعادة استخدامه في buildTimeSnapshot (تفادي N+1)
        $this->lockedSlot = $slot->fresh();
    }

   public function buildTimeSnapshot(BookingData $data): array
{
    // جلب الـ slot مع علاقة الـ availability بناءً على الـ migrations الخاصة بك
    // (تأكد أن اسم دالة العلاقة داخل موديل ListingSlot هو 'availability')
    $slot = $this->lockedSlot ?? ListingSlot::with('availability')->find($data->slotId);

    // 1. التحقق من وجود الـ slot والـ availability المرتبطة به في قاعدة البيانات
    if (!$slot || !$slot->availability) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'slot_id' => ['الفترة الزمنية المطلوبة غير موجودة أو غير متاحة حالياً.']
        ]);
    }

    // 2. حماية إضافية (من جدول availability): التأكد أن اليوم غير مغلق يدوياً من الـ Vendor
    if ($slot->availability->is_blocked) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'booked_date' => ['عذراً، هذا اليوم تم إغلاقه من قبل مقدم الخدمة ولا يستقبل حجوزات.']
        ]);
    }

    // 3. حماية إضافية (من جدول slots): التأكد من وجود سعة متبقية للحجز
    if ($slot->remaining_capacity <= 0) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'slot_id' => ['عذراً، هذه الفترة ممتلئة بالكامل ولا يوجد مقاعد متبقية.']
        ]);
    }

    // 4. التحقق الحاسم: مقارنة التاريخ المرسل مع الـ available_date في جدولك
    // نقوم بعمل parse للتأكد من مطابقة الصيغة (Y-m-d) تماماً دون مشاكل كاستنج
    $requestedDate = \Carbon\Carbon::parse($data->bookedDate)->format('Y-m-d');
    $availableDate = \Carbon\Carbon::parse($slot->availability->available_date)->format('Y-m-d');

    if ($requestedDate !== $availableDate) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'booked_date' => ['خطأ في البيانات: التاريخ المحدد لا يطابق يوم العرض الفعلي المتاح.']
        ]);
    }

    // 5. إذا مرت كل التحققات بنجاح، يتم إنشاء السناب شوت بأمان
    return [
        'booked_date'       => $availableDate, // نأخذ التاريخ المؤكد والمضمون من قاعدة البيانات
        'booked_start_time' => $slot->start_time, // الحقل كـ string مخزن بـ HH:MM:SS كما أصلحتها سابقاً
        'booked_end_time'   => $slot->end_time,
    ];
}
    public function buildTypeMetadata(BookingData $data): array
    {
        return [
            'event_type'  => $data->metadata['event_type'] ?? null,
            'guest_count' => $data->metadata['guest_count'] ?? null,
            'setup_needs' => $data->metadata['setup_needs'] ?? null,
        ];
    }
}
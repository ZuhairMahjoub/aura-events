<?php

namespace App\Services\Booking\Strategies;

use App\Contracts\BookingStrategyInterface;
use App\DTOs\BookingData;
use App\Models\ListingSlot;
use App\Models\ListingVariant;
use App\Models\Booking;
use Illuminate\Validation\ValidationException;

/**
 * استراتيجية موحّدة لحجز الصالات (Halls) وخدمات الفريلانس (Services).
 *
 * تتعامل مع:
 * - الصالات (Halls): تحتاج slot زمني محدد
 * - خدمات الفريلانس (Services): تحتاج slot زمني محدد
 *
 * كلاهما يعتمد على:
 * - listing_slot_id: الوقت المحدد
 * - booked_date: التاريخ
 * - listing_variant.capacity: السعة الاستيعابية الكلية (كم شخص/وحدة تسع)
 *   تُستخدم فقط كفحص منطقية لعدد quantity المطلوب بهذا الحجز الواحد —
 *   وليست آلية لتقسيم الـ slot بين حجوزات متعددة.
 * - listing_slot.remaining_capacity: مفتاح إشغال/تفريغ للـ slot نفسه.
 *   ينقص بمقدار ثابت = 1 عند كل حجز ناجح (كل حجز يشغل الـ slot بالكامل
 *   بغض النظر عن الكمية)، ويزيد بمقدار ثابت = 1 عند الإلغاء/الرفض.
 *
 * (سابقاً: هذا الملف كان يخص service فقط، ويوجد HallBookingStrategy منفصل
 * بمنطق شبه مطابق. تم توحيدهما هنا لمنع انحراف السلوك بين النوعين
 * مستقبلاً — BookingStrategyFactory يوجّه كل من 'hall' و 'service' لنفس
 * هذا الكلاس.)
 *
 * ملاحظة هامة على فحص "السعة المتبقية" (remaining_capacity <= 0):
 * هذا الفحص يجب أن يحدث فقط داخل reserveCapacity() *قبل* الـ decrement
 * (لأن حجز التعارض overlapping أعلاه يكفي فعلياً للكشف عن أن الـ slot
 * مشغول، والفحص المباشر على العمود هو طبقة حماية إضافية على نفس القيمة
 * القديمة). لا يجوز تكرار هذا الفحص داخل buildTimeSnapshot()، لأن تلك
 * الدالة تُستدعى *بعد* نجاح reserveCapacity() (أي بعد أن أصبحت القيمة
 * صفراً بسبب decrement()، فيرفض الحجز نفسه الذي أنشأه للتو).
 */
class ServiceBookingStrategy implements BookingStrategyInterface
{
    private ?ListingSlot $lockedSlot = null;

    public function validate(BookingData $data): void
    {
        // التحقق من وجود slot محدد
        if (empty($data->slotId)) {
            throw ValidationException::withMessages([
                'listing_slot_id' => 'الحجز يتطلب تحديد time slot.',
            ]);
        }

        // التحقق من وجود تاريخ محدد
        if (empty($data->bookedDate)) {
            throw ValidationException::withMessages([
                'booked_date' => 'الحجز يتطلب تحديد تاريخ.',
            ]);
        }

        // إصلاح TOCTOU: فحص التعارض الفعلي وفحص السعة يبقيان داخل
        // reserveCapacity() تحت lockForUpdate. الفحص هنا للتحقق البنيوي فقط
        // — لو صار هون، طلبان متزامنان يقدروا يتجاوزوه معاً قبل أي قفل.
    }

    public function reserveCapacity(BookingData $data): void
    {
        // ── Lock هو نقطة التزامن الوحيدة والحقيقية (TOCTOU-safe) ──────────
        $slot = ListingSlot::with('availability')->lockForUpdate()->findOrFail($data->slotId);

        // ── فحص الانتماء: هل هذا الـ slot يخص فعلاً الـ variant المطلوب؟ ──
        if (! $slot->availability || $slot->availability->listing_variant_id !== $data->variantId) {
            throw ValidationException::withMessages([
                'listing_slot_id' => 'الفترة الزمنية المحددة لا تنتمي لهذا العرض.',
            ]);
        }

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

        // فحص السعة الحقيقي: quantity المطلوبة مقابل listing_variant.capacity
        // (وليس slot.remaining_capacity، الذي بات يعمل فقط كمفتاح
        // إشغال/تفريغ للـ slot نفسه — راجع تعليق الكلاس أعلاه).
        $variant = ListingVariant::lockForUpdate()->find($data->variantId);

        if (! $variant) {
            throw ValidationException::withMessages([
                'listing_variant_id' => 'العرض المطلوب غير موجود.',
            ]);
        }

        if ($variant->capacity !== null && $variant->capacity < $data->quantity) {
            throw ValidationException::withMessages([
                'quantity' => "الطاقة الاستيعابية غير كافية. المتاح: {$variant->capacity}.",
            ]);
        }

        // التحقق من أن الـ slot نفسه لسا متاح (لم يُشغَل بحجز سابق) — يحدث
        // هنا فقط، قبل الـ decrement، وليس مكرراً في buildTimeSnapshot().
        if ($slot->remaining_capacity <= 0) {
            throw ValidationException::withMessages([
                'listing_slot_id' => 'هذا الـ Slot غير متاح حالياً.',
            ]);
        }

        // نقص ثابت = 1 على الـ slot (كل حجز يشغل الـ slot بالكامل، بغض
        // النظر عن quantity — الفرق الجوهري عن المنطق القديم الذي كان
        // ينقص بمقدار quantity).
        $slot->decrement('remaining_capacity', 1);

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

        // ملاحظة: فحص remaining_capacity <= 0 حُذف من هنا عمداً — كان يتحقق
        // *بعد* أن ينقصها reserveCapacity() بنفس الحجز الحالي، فيرفض الحجز
        // الذي أنشأه للتو دائماً. الفحص الصحيح موجود فقط في reserveCapacity()
        // قبل الـ decrement.

        // 3. التحقق الحاسم: مقارنة التاريخ المرسل مع الـ available_date في جدولك
        // نقوم بعمل parse للتأكد من مطابقة الصيغة (Y-m-d) تماماً دون مشاكل كاستنج
        $requestedDate = \Carbon\Carbon::parse($data->bookedDate)->format('Y-m-d');
        $availableDate = \Carbon\Carbon::parse($slot->availability->available_date)->format('Y-m-d');

        if ($requestedDate !== $availableDate) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'booked_date' => ['خطأ في البيانات: التاريخ المحدد لا يطابق يوم العرض الفعلي المتاح.']
            ]);
        }

        // 4. إذا مرت كل التحققات بنجاح، يتم إنشاء السناب شوت بأمان
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

    /**
     * عكس reserveCapacity(): إعادة السعة الاستيعابية للـ slot عند
     * الإلغاء/الرفض — increment ثابت = 1 (يطابق تماماً النقص الثابت الذي
     * حصل عند الحجز، وليس بمقدار booking->quantity كما كان سابقاً).
     * تُستدعى من BookingService::releaseCapacity().
     */
    public function release(Booking $booking): void
    {
        if ($booking->listing_slot_id) {
            ListingSlot::lockForUpdate()
                ->find($booking->listing_slot_id)
                ?->increment('remaining_capacity', 1);
        }
    }
}
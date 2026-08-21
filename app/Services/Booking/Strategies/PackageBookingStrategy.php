<?php

namespace App\Services\Booking\Strategies;

use App\Contracts\BookingStrategyInterface;
use App\DTOs\BookingData;
use App\Models\Booking;
use App\Models\ListingVariant;
use App\Models\ListingSlot;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * إصلاحات مطبقة:
 * - 3.3: lockForUpdate صحيح على Variants (Lock جماعي مرتَّب لمنع Deadlock).
 * - 3.8: حماية من null في getFreelancersSnapshot.
 * - 3.9: withTrashed للكشف عن المكونات المحذوفة في validate().
 * - جديد: قفل sub-slots الزمنية (hall/service) بترتيب ثابت لمنع Deadlock
 *   بين باقات متزامنة تتشارك نفس الـ slots بترتيب معكوس.
 */
class PackageBookingStrategy implements BookingStrategyInterface
{
    public function validate(BookingData $data): void
    {
        if (empty($data->bookedDate)) {
            throw ValidationException::withMessages([
                'booked_date' => 'حجز التنسيق يتطلب تحديد تاريخ.',
            ]);
        }

        // إصلاح 3.9: استخدام withTrashed للكشف عن المكونات المحذوفة
        $packageVariant = ListingVariant::with([
            'packageItems' => fn($q) => $q->with([
                'includedVariant' => fn($q2) => $q2->withTrashed()->with('listing'),
            ]),
        ])->findOrFail($data->variantId);

        // إصلاح (Edge Case): إذا احتوت الباقة على مكوّن زمني (hall/service)
        // ولم يُحدَّد slotId، فإن reserveCapacity() لا تملك أي وسيلة لقفل أو
        // حجز ذلك المكوّن فعلياً (لا يوجد عمود سعة على ListingAvailability
        // نفسها، فقط is_blocked) — ما كان يسمح بحجز نفس الباقة لعدة زبائن
        // بنفس اليوم دون أي تعارض حقيقي. نمنع هذا المسار غير الآمن من الأساس
        // بدل محاولة "تخمين" سعة غير موجودة.
        $hasTimeDependentItems = $packageVariant->packageItems->contains(
            fn($item) => $item->includedVariant
                && !$item->includedVariant->trashed()
                && in_array($item->includedVariant->listing->listing_type, ['hall', 'service'])
        );

        if ($hasTimeDependentItems && empty($data->slotId)) {
            throw ValidationException::withMessages([
                'listing_slot_id' => 'هذه الباقة تحتوي على مكونات زمنية (قاعة/خدمة)، يجب تحديد time slot لضمان حجز الوقت فعلياً ومنع التعارض مع حجوزات أخرى.',
            ]);
        }

        // إصلاح (Edge Case): فحص أن الفريلانسرز المرتبطين بهذه الباقة غير
        // مرتبطين بحجز باقة آخر فعّال (pending/accepted/confirmed) لنفس
        // التاريخ. لا يوجد جدول حجوزات منفصل للفريلانسر، لذا نعتمد على
        // الـ snapshot المخزَّن داخل metadata->booking_items لأي حجز سابق.
        $this->validateFreelancersAvailability($packageVariant, $data->bookedDate);

        $mainSlot = $data->slotId ? ListingSlot::find($data->slotId) : null;

        foreach ($packageVariant->packageItems as $item) {
            $variant = $item->includedVariant;

            // فحص المكوّن المحذوف
            if (!$variant || $variant->trashed()) {
                throw ValidationException::withMessages([
                    'package' => 'أحد مكونات الباقة لم يعد متاحاً. يرجى التواصل مع المزود.',
                ]);
            }

            $type        = $variant->listing->listing_type;
            $requiredQty = $item->quantity * $data->quantity;

            if ($type === 'physical_product') {
                if ($variant->price_type === 'hourly' && $mainSlot) {
                    // نفس منطق hall/service: نحاول أولاً إيجاد sub-slot بنفس
                    // تاريخ/وقت الباقة الرئيسي. إذا وُجد، السعة تُفحص عليه
                    // (مستقل لكل يوم). إذا لم يوجد، fallback لفحص
                    // stock_quantity مباشرة (نفس سلوك fixed) بدل رفض الحجز
                    // بالكامل — قرار متعمد لتفادي فرض شرط صارم على مزوّدين
                    // لم يضيفوا slots لكل تاريخ ممكن.
                    $subSlotExists = \App\Models\ListingSlot::whereHas('availability', function ($q) use ($variant, $data) {
                            $q->where('listing_variant_id', $variant->id)
                              ->where('available_date', $data->bookedDate);
                        })
                        ->where('start_time', $mainSlot->getRawOriginal('start_time'))
                        ->exists();

                    if ($subSlotExists) {
                        $this->validateSubComponentSlot($variant, $data->bookedDate, $mainSlot, $requiredQty);
                    } elseif ($variant->stock_quantity < $requiredQty) {
                        throw ValidationException::withMessages([
                            'quantity' => "المكون [" . ($variant->variant_name['ar'] ?? $variant->variant_name) . "] غير متوفر بالكمية المطلوبة في المخزون.",
                        ]);
                    }
                } elseif ($variant->stock_quantity < $requiredQty) {
                    throw ValidationException::withMessages([
                        'quantity' => "المكون [" . ($variant->variant_name['ar'] ?? $variant->variant_name) . "] غير متوفر بالكمية المطلوبة في المخزون.",
                    ]);
                }
            } elseif (in_array($type, ['hall', 'service'])) {
                if ($mainSlot) {
                    $this->validateSubComponentSlot($variant, $data->bookedDate, $mainSlot, $data->quantity);
                } else {
                    $this->validateSubComponentDailyAvailability($variant, $data->bookedDate);
                }
            }
        }
    }

    private function validateSubComponentDailyAvailability($variant, $date): void
    {
        $isAvailable = \App\Models\ListingAvailability::where('listing_variant_id', $variant->id)
            ->where('available_date', $date)
            ->where('is_blocked', false)
            ->exists();

        if (!$isAvailable) {
            throw ValidationException::withMessages([
                'booked_date' => "المكون [{$variant->variant_name}] غير متاح في التاريخ المختار ({$date}).",
            ]);
        }
    }

    /**
     * إصلاح 3.3: Lock جماعي مرتَّب أبجدياً لمنع Deadlock.
     */
    public function reserveCapacity(BookingData $data): void
    {
        $packageVariant = ListingVariant::with([
            'packageItems.includedVariant.listing',
        ])->findOrFail($data->variantId);

        $mainSlot = $data->slotId
            ? ListingSlot::lockForUpdate()->findOrFail($data->slotId)
            : null;

        // جمع IDs المنتجات المادية للـ Lock الجماعي
        $physicalVariantIds = $packageVariant->packageItems
            ->filter(fn($item) => $item->includedVariant->listing->listing_type === 'physical_product')
            ->pluck('includedVariant.id')
            ->toArray();

        // ── Lock جماعي بترتيب ثابت لمنع Deadlock ───────────────────────────
        sort($physicalVariantIds);
        $lockedVariants = ListingVariant::whereIn('id', $physicalVariantIds)
            ->lockForUpdate()
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        // ── إصلاح Deadlock: العناصر الزمنية (hall/service) كانت تُقفَل
        // واحدة تلو الأخرى داخل foreach بترتيب اعتباطي (ترتيب packageItems
        // في الـ DB)، بعكس المنتجات المادية المُرتَّبة أبجدياً قبل القفل
        // الجماعي. لو حجزت باقتان متزامنتان نفس مجموعة الـ sub-slots لكن
        // بترتيب items معكوس، يحدث deadlock حقيقي بين الـ transactions.
        // الحل: تحديد كل sub-slot IDs المطلوبة مسبقاً (بدون lock)، ترتيبها،
        // ثم قفلها دفعة واحدة بنفس ترتيب القفل الجماعي للمنتجات المادية.
        //
        // ملاحظة: physical_product بسعر hourly مضاف هنا أيضاً — إذا وُجد
        // sub-slot بنفس تاريخ/وقت الباقة، يُقفَل ويُخصَم منه (بدل
        // stock_quantity)، بنفس منطق hall/service بالضبط. ──
        $timeDependentItems = $packageVariant->packageItems
            ->filter(fn($item) => in_array($item->includedVariant->listing->listing_type, ['hall', 'service'])
                || ($item->includedVariant->listing->listing_type === 'physical_product' && $item->includedVariant->price_type === 'hourly'));

        $lockedSubSlots = collect();

        if ($mainSlot && $timeDependentItems->isNotEmpty()) {
            $subSlotIds = ListingSlot::whereHas('availability', function ($q) use ($timeDependentItems, $data) {
                    $q->whereIn('listing_variant_id', $timeDependentItems->pluck('includedVariant.id'))
                      ->where('available_date', $data->bookedDate);
                })
                ->where('start_time', $mainSlot->getRawOriginal('start_time'))
                ->pluck('id')
                ->sort()
                ->values()
                ->toArray();

            if (! empty($subSlotIds)) {
                $lockedSubSlots = ListingSlot::whereIn('id', $subSlotIds)
                    ->with('availability:id,listing_variant_id')
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->get()
                    ->keyBy(function ($slot) {
                        return $slot->availability->listing_variant_id;
                    });
            }
        }

        foreach ($packageVariant->packageItems as $item) {
            $variant     = $item->includedVariant;
            $type        = $variant->listing->listing_type;
            $requiredQty = $item->quantity * $data->quantity;

            if ($type === 'physical_product') {
                $isHourlyWithSubSlot = $variant->price_type === 'hourly'
                    && $mainSlot
                    && $lockedSubSlots->has($variant->id);

                if ($isHourlyWithSubSlot) {
                    // نفس مسار hall/service: الخصم من remaining_capacity
                    // الخاص بالـ sub-slot المستقل لهذا اليوم بالذات،
                    // stock_quantity يبقى ثابتاً مرجعياً ولا يُخصم.
                    $this->reserveSubComponentSlotFromLocked($variant, $lockedSubSlots, $requiredQty);
                } else {
                    // fixed، أو hourly بدون sub-slot متاح بنفس وقت الباقة
                    // (fallback): الخصم المباشر من stock_quantity.
                    $lockedVariant = $lockedVariants->get($variant->id);

                    if (!$lockedVariant || $lockedVariant->stock_quantity < $requiredQty) {
                        throw ValidationException::withMessages([
                            'quantity' => "المكون [" . ($variant->variant_name['ar'] ?? '') . "] نفد مخزونه.",
                        ]);
                    }

                    // تحديث مباشر على الـ instance المُقفَل (لا re-fetch)
                    $lockedVariant->decrement('stock_quantity', $requiredQty);
                }

            } elseif (in_array($type, ['hall', 'service']) && $mainSlot) {
                $this->reserveSubComponentSlotFromLocked($variant, $lockedSubSlots, $requiredQty);
            }
        }
    }

    /**
     * إصدار محدَّث يستخدم الـ sub-slot المُقفَل مسبقاً (بترتيب ثابت) بدل
     * تنفيذ lockForUpdate منفصل لكل عنصر داخل الحلقة (إصلاح Deadlock أعلاه).
     */
    private function reserveSubComponentSlotFromLocked($variant, Collection $lockedSubSlots, int $requiredQty): void
    {
        $subSlot = $lockedSubSlots->get($variant->id);

        if (!$subSlot || $subSlot->remaining_capacity < $requiredQty) {
            throw ValidationException::withMessages([
                'listing_slot_id' => "المكون [" . ($variant->variant_name['ar'] ?? $variant->variant_name) . "] غير متاح في وقت الباقة.",
            ]);
        }

        $subSlot->decrement('remaining_capacity', $requiredQty);
    }

    /**
     * @deprecated أُبقي عليها فقط في حال استُدعيت من مسار قديم خارجي؛
     * المسار الفعلي الجديد يستخدم reserveSubComponentSlotFromLocked أعلاه
     * ضمن قفل جماعي مرتَّب لمنع الـ Deadlock.
     */
    private function reserveSubComponentSlotWithLock($variant, $date, $mainSlot, $quantity): void
    {
        $subSlot = ListingSlot::whereHas('availability', function ($q) use ($variant, $date) {
                $q->where('listing_variant_id', $variant->id)
                  ->where('available_date', $date);
            })
            ->where('start_time', $mainSlot->getRawOriginal('start_time'))
            ->lockForUpdate()
            ->first();

        if (!$subSlot || $subSlot->remaining_capacity < $quantity) {
            throw ValidationException::withMessages([
                'listing_slot_id' => "المكون [" . ($variant->variant_name['ar'] ?? $variant->variant_name) . "] غير متاح في وقت الباقة.",
            ]);
        }

        $subSlot->decrement('remaining_capacity', $quantity);
    }

    public function buildTypeMetadata(BookingData $data): array
    {
        $packageVariant = ListingVariant::with([
            'packageItems.includedVariant.listing',
            'packageFreelancers.freelancer',
        ])->find($data->variantId);

        $mainSlot = $data->slotId ? ListingSlot::find($data->slotId) : null;

        $items = $packageVariant->packageItems->map(function ($item) use ($data, $mainSlot) {
            $variant = $item->includedVariant;
            $type    = $variant->listing->listing_type;
            $isTimeDependent = in_array($type, ['hall', 'service']);

            // لمكوّن physical_product بسعر hourly، نحدد أي sub-slot انخصم
            // منه فعلياً (نفس الفحص المستخدم في reserveCapacity) — عشان
            // release() يرجع للمكان الصحيح بالضبط بدل التخمين.
            $usedSlotId = null;
            if ($type === 'physical_product' && $variant->price_type === 'hourly' && $mainSlot) {
                $usedSlotId = ListingSlot::whereHas('availability', function ($q) use ($variant, $data) {
                        $q->where('listing_variant_id', $variant->id)
                          ->where('available_date', $data->bookedDate);
                    })
                    ->where('start_time', $mainSlot->getRawOriginal('start_time'))
                    ->value('id');
            }

            return [
                'type'               => 'item',
                'listing_variant_id' => $item->included_variant_id,
                'item_name'          => $variant->variant_name,
                'quantity'           => $item->quantity * $data->quantity,
                'price_at_booking'   => $variant->price,
                'is_time_dependent'  => $isTimeDependent,
                // slot_id الفعلي الذي خُصم منه هذا المكوّن (hall/service
                // دائماً، physical_product/hourly فقط إذا وُجد sub-slot،
                // null يعني تم الخصم من stock_quantity مباشرة).
                'used_slot_id'       => $isTimeDependent ? $data->slotId : $usedSlotId,
            ];
        })->toArray();

        return [
            'is_coordination_package' => true,
            'booking_items'           => array_merge($items, $this->getFreelancersSnapshot($packageVariant)),
        ];
    }

    private function validateSubComponentSlot($variant, $date, $mainSlot, $quantity): void
    {
        $hasSlot = ListingSlot::whereHas('availability', function ($q) use ($variant, $date) {
            $q->where('listing_variant_id', $variant->id)
              ->where('available_date', $date);
        })
            ->where('start_time', $mainSlot->getRawOriginal('start_time'))
            ->where('remaining_capacity', '>=', $quantity)
            ->exists();

        if (!$hasSlot) {
            throw ValidationException::withMessages([
                'listing_slot_id' => "المكون [{$variant->variant_name}] غير متاح في نفس وقت الباقة.",
            ]);
        }
    }

    /**
     * إصلاح 3.8: حماية من null عند Soft-delete على الـ Freelancer.
     */
    private function getFreelancersSnapshot($packageVariant): array
    {
        $packageVariant->loadMissing('packageFreelancers.freelancer');

        return $packageVariant->packageFreelancers
            ->filter(fn($f) => $f->freelancer !== null)   // ✅ حماية من null
            ->map(fn($f) => [
                'type'          => 'freelancer',
                'freelancer_id' => $f->freelancer_id,
                'name'          => $f->freelancer->name ?? 'مستقل غير متاح',
                'contract_id'   => $f->contract_id,
                'is_active'     => $f->freelancer !== null,
            ])->values()->toArray();
    }

    public function buildTimeSnapshot(BookingData $data): array
    {
        $slot = $data->slotId ? ListingSlot::find($data->slotId) : null;

        // إصلاح حرج: start_time/end_time هما string جاهز، لا Carbon.
        return [
            'booked_date'       => $data->bookedDate,
            'booked_start_time' => $slot?->start_time,
            'booked_end_time'   => $slot?->end_time,
        ];
    }

    /**
     * فحص (Edge Case): يمنع ربط نفس الفريلانسر بباقتين تنحجزان لنفس التاريخ
     * بحالة فعّالة (pending/accepted/confirmed). لا يوجد جدول حجوزات مستقل
     * للفريلانسر، فنبحث ضمن metadata->booking_items للحجوزات السابقة من
     * نوع 'package' (يعمل بفضل JSON_CONTAINS على MySQL — يدعم Containment
     * الجزئي على عناصر الـ array، فلا حاجة لمطابقة كل المفاتيح).
     */
    private function validateFreelancersAvailability($packageVariant, ?string $bookedDate): void
    {
        if (empty($bookedDate)) {
            return;
        }

        $packageVariant->loadMissing('packageFreelancers.freelancer');

        $freelancerIds = $packageVariant->packageFreelancers
            ->pluck('freelancer_id')
            ->filter()
            ->unique();

        foreach ($freelancerIds as $freelancerId) {
            $isBusy = Booking::where('booking_type', 'package')
                ->where('booked_date', $bookedDate)
                ->whereIn('status', ['pending', 'accepted', 'confirmed'])
                ->whereJsonContains('metadata->booking_items', [
                    'type'          => 'freelancer',
                    'freelancer_id' => $freelancerId,
                ])
                ->exists();

            if ($isBusy) {
                throw ValidationException::withMessages([
                    'package' => 'أحد الفريلانسرز ضمن هذه الباقة لديه حجز آخر فعّال بنفس التاريخ، لا يمكن تأكيد الحجز.',
                ]);
            }
        }
    }

    /**
     * عكس reserveCapacity(): تُستدعى من BookingService::releaseCapacity()
     * عند الإلغاء/الرفض. نعتمد على الـ snapshot المخزَّن في
     * metadata->booking_items وقت الحجز (وليس إعادة جلب packageItems
     * الحالية) لأن مكوّنات الباقة قد تتغيّر لاحقاً عبر SyncPackageItemsAction؛
     * يجب إعادة بالضبط ما خُصم وقتها، لا ما هو موجود بالباقة الآن.
     *
     * كل عنصر بالـ snapshot يحمل used_slot_id صريحاً (مُحدَّد وقت الحجز في
     * buildTypeMetadata): إذا موجود، الإرجاع لـ remaining_capacity لذلك
     * الـ slot بالذات (hall/service دائماً، physical_product/hourly إذا
     * وُجد sub-slot وقتها). إذا null، الإرجاع لـ stock_quantity مباشرة
     * (physical_product/fixed، أو hourly بدون sub-slot متاح وقتها — نفس
     * المسار الذي استُخدم في reserveCapacity وقتها بالضبط).
     */
    public function release(Booking $booking): void
    {
        $bookingItems = collect($booking->metadata['booking_items'] ?? [])
            ->filter(fn($entry) => ($entry['type'] ?? null) === 'item');

        if ($bookingItems->isEmpty()) {
            return;
        }

        $variantIds = $bookingItems->pluck('listing_variant_id')->unique()->sort()->values()->toArray();

        // ── Lock جماعي مرتَّب لكل المكونات المادية (نفس منطق reserveCapacity) ──
        $variants = ListingVariant::with('listing')
            ->whereIn('id', $variantIds)
            ->lockForUpdate()
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        // Lock جماعي مرتَّب لكل الـ slots المخزَّنة صراحة في used_slot_id
        // (بدل إعادة اشتقاقها من التاريخ/الوقت — أدق وأضمن ضد أي تغيير
        // لاحق على بيانات الـ availabilities/slots).
        $slotIds = $bookingItems->pluck('used_slot_id')->filter()->unique()->sort()->values()->toArray();

        $lockedSlots = collect();
        if (! empty($slotIds)) {
            $lockedSlots = ListingSlot::whereIn('id', $slotIds)
                ->lockForUpdate()
                ->orderBy('id')
                ->get()
                ->keyBy('id');
        }

        foreach ($bookingItems as $entry) {
            $quantity   = (int) ($entry['quantity'] ?? 0);
            $usedSlotId = $entry['used_slot_id'] ?? null;

            // مسار الـ slot لا يعتمد على وجود الـ variant حالياً — الـ slot
            // نفسه هو الذي خُصم منه، ويبقى صالحاً للإرجاع حتى لو حُذف الـ
            // variant لاحقاً (أو تغيّرت مكوناته عبر SyncPackageItemsAction).
            if ($usedSlotId && $lockedSlots->has($usedSlotId)) {
                $lockedSlots->get($usedSlotId)->increment('remaining_capacity', $quantity);
                continue;
            }

            // مسار stock_quantity: يحتاج الـ variant لا يزال موجوداً.
            $variant = $variants->get($entry['listing_variant_id']);
            if (!$variant) {
                continue;
            }

            if ($variant->listing?->listing_type === 'physical_product') {
                $variant->increment('stock_quantity', $quantity);
            }
        }
    }
}
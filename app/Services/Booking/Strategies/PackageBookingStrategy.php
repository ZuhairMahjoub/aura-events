<?php

namespace App\Services\Booking\Strategies;

use App\Contracts\BookingStrategyInterface;
use App\DTOs\BookingData;
use App\Models\ListingVariant;
use App\Models\ListingSlot;
use Illuminate\Validation\ValidationException;

/**
 * إصلاحات مطبقة:
 * - 3.3: lockForUpdate صحيح على Variants (Lock جماعي مرتَّب لمنع Deadlock).
 * - 3.8: حماية من null في getFreelancersSnapshot.
 * - 3.9: withTrashed للكشف عن المكونات المحذوفة في validate().
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
                if ($variant->stock_quantity < $requiredQty) {
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

        foreach ($packageVariant->packageItems as $item) {
            $variant     = $item->includedVariant;
            $type        = $variant->listing->listing_type;
            $requiredQty = $item->quantity * $data->quantity;

            if ($type === 'physical_product') {
                $lockedVariant = $lockedVariants->get($variant->id);

                if (!$lockedVariant || $lockedVariant->stock_quantity < $requiredQty) {
                    throw ValidationException::withMessages([
                        'quantity' => "المكون [" . ($variant->variant_name['ar'] ?? '') . "] نفد مخزونه.",
                    ]);
                }

                // تحديث مباشر على الـ instance المُقفَل (لا re-fetch)
                $lockedVariant->decrement('stock_quantity', $requiredQty);

            } elseif (in_array($type, ['hall', 'service']) && $mainSlot) {
                $this->reserveSubComponentSlotWithLock($variant, $data->bookedDate, $mainSlot, $data->quantity);
            }
        }
    }

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

        $items = $packageVariant->packageItems->map(fn($item) => [
            'type'               => 'item',
            'listing_variant_id' => $item->included_variant_id,
            'item_name'          => $item->includedVariant->variant_name,
            'quantity'           => $item->quantity * $data->quantity,
            'price_at_booking'   => $item->includedVariant->price,
            'is_time_dependent'  => in_array($item->includedVariant->listing->listing_type, ['hall', 'service']),
        ])->toArray();

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

        return [
            'booked_date'       => $data->bookedDate,
            'booked_start_time' => $slot?->start_time?->format('H:i:s'),
            'booked_end_time'   => $slot?->end_time?->format('H:i:s'),
        ];
    }
}

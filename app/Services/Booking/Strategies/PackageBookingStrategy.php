<?php

namespace App\Services\Booking\Strategies;

use App\Contracts\BookingStrategyInterface;
use App\DTOs\BookingData;
use App\Models\Booking;
use App\Models\ListingVariant;
use App\Models\ListingSlot;
use App\Models\FreelancerBlockedDate;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PackageBookingStrategy implements BookingStrategyInterface
{
    public function validate(BookingData $data): void
    {
        if (empty($data->bookedDate)) {
            throw ValidationException::withMessages([
                'booked_date' => 'حجز التنسيق يتطلب تحديد تاريخ.',
            ]);
        }

        $packageVariant = ListingVariant::with([
            'packageItems' => fn($q) => $q->with([
                'includedVariant' => fn($q2) => $q2->withTrashed()->with('listing'),
            ]),
        ])->findOrFail($data->variantId);

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

        $this->validateFreelancersAvailability($packageVariant, $data->bookedDate, $data);

        $mainSlot = $data->slotId ? ListingSlot::find($data->slotId) : null;

        foreach ($packageVariant->packageItems as $item) {
            $variant = $item->includedVariant;

            if (!$variant || $variant->trashed()) {
                throw ValidationException::withMessages([
                    'package' => 'أحد مكونات الباقة لم يعد متاحاً. يرجى التواصل مع المزود.',
                ]);
            }

            $type = $variant->listing->listing_type;

            if (in_array($type, ['hall', 'service'])) {
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

    public function reserveCapacity(BookingData $data): void
    {
        $packageVariant = ListingVariant::with([
            'packageItems.includedVariant.listing',
        ])->findOrFail($data->variantId);

        $mainSlot = $data->slotId
            ? ListingSlot::lockForUpdate()->findOrFail($data->slotId)
            : null;

        $timeDependentItems = $packageVariant->packageItems
            ->filter(fn($item) => in_array($item->includedVariant->listing->listing_type, ['hall', 'service']));

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
            $variant = $item->includedVariant;
            $type    = $variant->listing->listing_type;
            
            // نجلب الكمية المطلوبة لنحجز بها الصالة/الخدمة فقط
            $requiredQty = $this->getRequestedQuantity($item, $data); 

            if (in_array($type, ['hall', 'service']) && $mainSlot) {
                $this->reserveSubComponentSlotFromLocked($variant, $lockedSubSlots, $requiredQty);
            }
        }
    }

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

   public function buildTypeMetadata(BookingData $data): array
    {
        $packageVariant = ListingVariant::with([
            'packageItems.includedVariant.listing.images', 
            'packageFreelancers.freelancer',
        ])->find($data->variantId);

        $mainSlot = $data->slotId ? ListingSlot::find($data->slotId) : null;

        $items = $packageVariant->packageItems->map(function ($item) use ($data, $mainSlot) {
            $variant = $item->includedVariant;
            $type    = $variant->listing->listing_type;
            $isTimeDependent = in_array($type, ['hall', 'service']);

            $imageUrl = $variant->listing?->images?->first()?->full_url ?? null;

            return [
                'type'               => 'item',
                'listing_variant_id' => $item->included_variant_id,
                'item_name'          => $variant->variant_name,
                'image'              => $imageUrl, // 🚀 إضافة رابط الصورة هنا للفاتورة
                'quantity'           => $this->getRequestedQuantity($item, $data),
                'price_at_booking'   => $variant->price,
                'is_time_dependent'  => $isTimeDependent,
                'used_slot_id'       => $isTimeDependent ? $data->slotId : null,
            ];
        })->toArray();

        return [
            'is_coordination_package' => true,
            'booking_items'           => array_merge($items, $this->getFreelancersSnapshot($packageVariant, $data)),
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

  private function getFreelancersSnapshot($packageVariant, BookingData $data): array
    {
        $packageVariant->loadMissing(['packageFreelancers.freelancer.user']);
        $selectedIds = $data->metadata['custom_freelancers'] ?? null;

        return $packageVariant->packageFreelancers
            ->filter(fn($f) => $f->freelancer !== null)
            ->filter(fn($f) => $selectedIds === null || in_array($f->freelancer_id, $selectedIds))
            ->map(function($f) {
                $freelancerName = $f->freelancer->name 
                               ?? $f->freelancer->brand_name 
                               ?? trim(($f->freelancer->user->first_name ?? '') . ' ' . ($f->freelancer->user->last_name ?? '')) 
                               ?: 'مستقل'; // إذا فشل كل شيء يكتب "مستقل"

                return [
                    'type'          => 'freelancer',
                    'freelancer_id' => $f->freelancer_id,
                    'name'          => $freelancerName,    
                    'contract_id'   => $f->contract_id,
                    'is_active'     => true,
                ];
            })->values()->toArray();
    }

    public function buildTimeSnapshot(BookingData $data): array
    {
        $slot = $data->slotId ? ListingSlot::find($data->slotId) : null;
        return [
            'booked_date'       => $data->bookedDate,
            'booked_start_time' => $slot?->start_time,
            'booked_end_time'   => $slot?->end_time,
        ];
    }

    private function validateFreelancersAvailability($packageVariant, ?string $bookedDate, BookingData $data): void
    {
        if (empty($bookedDate)) {
            return;
        }

        $packageVariant->loadMissing('packageFreelancers.freelancer');
        $selectedIds = $data->metadata['custom_freelancers'] ?? null;

        $freelancerIds = $packageVariant->packageFreelancers
            ->pluck('freelancer_id')
            ->filter()
            ->filter(fn($id) => $selectedIds === null || in_array($id, $selectedIds)) 
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
                    'package' => 'أحد طاقم العمل المختارين لديه حجز آخر فعّال بنفس التاريخ، لا يمكن تأكيد الحجز.',
                ]);
            }
        }
    }

    public function release(Booking $booking): void
    {
        $bookingItems = collect($booking->metadata['booking_items'] ?? [])
            ->filter(fn($entry) => ($entry['type'] ?? null) === 'item');

        if ($bookingItems->isEmpty()) {
            return;
        }

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

            if ($usedSlotId && $lockedSlots->has($usedSlotId)) {
                $lockedSlots->get($usedSlotId)->increment('remaining_capacity', $quantity);
            }
        }
    }

    private function getRequestedQuantity($item, BookingData $data): int
    {
        $customItems = $data->metadata['custom_items'] ?? []; 
        foreach ($customItems as $custom) {
            if ($custom['variant_id'] === $item->included_variant_id) {
                return min((int)$custom['quantity'], $item->quantity);
            }
        }
        return $item->quantity;
    }
}
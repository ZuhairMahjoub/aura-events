<?php

namespace App\Actions\Arrangement;

use App\Actions\Listing\BulkInsertSlotsAction;
use App\Models\ListingVariant;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;


class SyncArrangementAvailabilitiesAction
{
    public function __construct(private BulkInsertSlotsAction $bulkInsertSlotsAction) {}

   
    public function execute(ListingVariant $variant, array $availabilitiesData, int $defaultCapacity = 1): void
    {
        $variant = ListingVariant::lockForUpdate()->findOrFail($variant->id);

        $baseCapacity = $variant->capacity ?? $variant->stock_quantity ?? $defaultCapacity;

        $sentIds = collect($availabilitiesData)->pluck('id')->filter()->values()->toArray();
        $toDelete = $variant->availabilities()->whereNotIn('id', $sentIds)->get();

        foreach ($toDelete as $availability) {
            foreach ($availability->slots as $slot) {
                $activeBookings = $slot->bookings()
                    ->whereNotIn('status', ['cancelled', 'rejected'])
                    ->count();

                if ($activeBookings > 0) {
                    throw ValidationException::withMessages([
                        'availabilities' => "لا يمكن حذف التاريخ {$availability->available_date} لوجود {$activeBookings} حجز نشط.",
                    ]);
                }
            }
        }

        $variant->availabilities()->whereNotIn('id', $sentIds)->delete();

        foreach ($availabilitiesData as $availabilityData) {
            $date = $availabilityData['available_date'];

            $query = $variant->availabilities()->where('available_date', $date);
            if (!empty($availabilityData['id'])) {
                $query->where('id', '!=', $availabilityData['id']);
            }

            if ($query->exists()) {
                throw ValidationException::withMessages([
                    'availabilities' => "التاريخ {$date} محجوز مسبقاً لهذا الترتيب.",
                ]);
            }

            if (!empty($availabilityData['id'])) {
                $availability = $variant->availabilities()->findOrFail($availabilityData['id']);
                $availability->update([
                    'available_date' => Carbon::parse($date)->format('Y-m-d'),
                    'is_blocked'     => $availabilityData['is_blocked'] ?? false,
                ]);
            } else {
                $availability = $variant->availabilities()->create([
                    'available_date' => Carbon::parse($date)->format('Y-m-d'),
                    'is_blocked'     => $availabilityData['is_blocked'] ?? false,
                ]);
            }

            $slots = collect($availabilityData['slots'] ?? [])
                ->map(function ($slot) use ($baseCapacity) {
                    $incomingRemaining = $slot['remaining_capacity'] ?? null;
                    
                    if ($incomingRemaining === null || $incomingRemaining < 0) {
                        $slot['remaining_capacity'] = $baseCapacity;
                    }
                    
                    return $slot;
                })
                ->toArray();

            $this->bulkInsertSlotsAction->execute($availability, $slots);
        }
    }

    
    public function buildAvailabilitiesFromRange(array $range): array
    {
        $startDate = Carbon::parse($range['start_date'])->startOfDay();
        $endDate   = Carbon::parse($range['end_date'])->startOfDay();
        $today     = Carbon::today();
        $isBlocked = $range['is_blocked'] ?? false;
        $slots     = $range['slots'] ?? [];

        if ($startDate->gt($endDate)) {
            return [];
        }

        $availabilities = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            if ($current->gte($today)) {
                $availabilities[] = [
                    'available_date' => $current->format('Y-m-d'),
                    'is_blocked'     => $isBlocked,
                    'slots'          => $slots,
                ];
            }
            $current->addDay();
        }

        return $availabilities;
    }
}
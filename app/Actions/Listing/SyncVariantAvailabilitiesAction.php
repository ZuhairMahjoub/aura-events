<?php

namespace App\Actions\Listing;

use App\Models\ListingVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;

class SyncVariantAvailabilitiesAction
{
    public function __construct(private BulkInsertSlotsAction $bulkInsertSlotsAction) {}

    public function execute(ListingVariant $variant, array $availabilitiesData): void
    {
        // لا نحتاج DB::transaction هنا — الـ transaction موجودة بالفعل في CreateListingAction/UpdateListingAction
        // الـ nested transaction كانت تسبب lockForUpdate يعطل الـ inserts

        // Fix #5: Lock the variant row to prevent race conditions
        $variant = ListingVariant::lockForUpdate()->findOrFail($variant->id);

        $sentIds = collect($availabilitiesData)->pluck('id')->filter()->values()->toArray();
        $toDelete = $variant->availabilities()->whereNotIn('id', $sentIds)->get();

        foreach ($toDelete as $availability) {
            foreach ($availability->slots as $slot) {
                $activeBookings = $slot->bookings()
                    ->whereNotIn('status', ['cancelled', 'rejected'])
                    ->count();

                if ($activeBookings > 0) {
                    throw ValidationException::withMessages([
                        'availabilities' => "لا يمكن حذف التاريخ {$availability->available_date} لوجود {$activeBookings} حجز نشط."
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
                    'availabilities' => "التاريخ {$date} محجوز مسبقاً لهذا الـ Variant."
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

            $this->bulkInsertSlotsAction->execute($availability, $availabilityData['slots'] ?? []);
        }
    }
}

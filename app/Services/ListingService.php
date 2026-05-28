<?php

namespace App\Services;

use App\Models\Listing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Exception;

class ListingService
{
  
    public function getAllListings(int $perPage = 15)
    {
        return Listing::with(['variants.availabilities.slots', 'category', 'district'])
            ->latest()
            ->paginate($perPage);
    }

    public function getListingById(Listing $listing): Listing
    {
        return $listing->load(['variants.availabilities.slots', 'category', 'district']);
    }

  
    public function createListingWithGraph(array $data): Listing
    {
        return DB::transaction(function () use ($data) {

            $listing = Listing::create([
                'provider_id'  => $data['provider_id'],
                'category_id'  => $data['category_id'],
                'district_id'  => $data['district_id'],
                'title'        => $data['title'],
                'description'  => $data['description'],
                'listing_type' => $data['listing_type'],
            ]);

            foreach ($data['variants'] as $variantData) {

                $variant = $listing->variants()->create(
                    $this->buildVariantPayload($variantData)
                );

                if (empty($variantData['availabilities'])) continue;

                $this->bulkInsertAvailabilitiesAndSlots($variant, $variantData['availabilities']);
            }

            return $listing->load('variants.availabilities.slots');
        });
    }

    

    public function updateListingWithGraph(Listing $listing, array $data): Listing
    {
        return DB::transaction(function () use ($listing, $data) {

            // ── 1. تحديث بيانات الـ Listing الأساسية ──────────────────────
            $listing->update(array_filter(
                [
                    'category_id'              => $data['category_id']              ?? null,
                    'district_id'              => $data['district_id']              ?? null,
                    'title'                    => $data['title']                    ?? null,
                    'description'              => $data['description']              ?? null,
                    'listing_type'             => $data['listing_type']             ?? null,
                    'cancel_before_acceptance' => $data['cancel_before_acceptance'] ?? null,
                    'cancel_after_acceptance'  => $data['cancel_after_acceptance']  ?? null,
                    'cancel_before_payment'    => $data['cancel_before_payment']    ?? null,
                ],
                fn($value) => !is_null($value)
            ));

            if (!isset($data['variants'])) {
                return $listing->load('variants.availabilities.slots');
            }

            // ── 2. حذف الـ Variants غير المرسلة ───────────────────────────
            $sentVariantIds = collect($data['variants'])
                ->pluck('id')
                ->filter()
                ->values()
                ->toArray();

           
            $listing->variants()
                ->whereNotIn('id', $sentVariantIds)
                ->get()
                ->each->delete();

            foreach ($data['variants'] as $variantData) {

                if (!empty($variantData['id'])) {
                    $variant = $listing->variants()->findOrFail($variantData['id']);
                    $variant->update($this->buildVariantPayload($variantData));
                } else {
                    $variant = $listing->variants()->create(
                        $this->buildVariantPayload($variantData)
                    );
                }

                if (!isset($variantData['availabilities'])) continue;

                $this->syncAvailabilities($variant, $variantData['availabilities']);
            }

            return $listing->load('variants.availabilities.slots');
        });
    }

   
    public function deleteListing(Listing $listing): bool
    {
        return $listing->delete();
    }

  
    private function buildVariantPayload(array $variantData): array
    {
        return [
            'variant_name'       => $variantData['variant_name'],
            'price'              => $variantData['price'],
            'currency'           => $variantData['currency']       ?? 'USD',
            'price_type'         => $variantData['price_type']     ?? 'fixed',
            'stock_quantity'     => $variantData['stock_quantity'] ?? null,
            'dynamic_attributes' => $this->mapServicesToColumns($variantData['services'] ?? []),
        ];
    }

    private function mapServicesToColumns(array $services): ?array
    {
        if (empty($services)) {
            return null;
        }

        if (isset($services[0]) && is_array($services[0]) && array_key_exists('key', $services[0])) {
            $mapped = [];
            foreach ($services as $service) {
                $mapped[$service['key']] = [
                    'value' => $service['value'],
                    'label' => $service['label'] ?? null,
                ];
            }
            return $mapped;
        }

        return $services;
    }


    private function bulkInsertAvailabilitiesAndSlots($variant, array $availabilitiesData): void
    {
        $availabilitiesToInsert = [];
        $slotsToInsert          = [];

        foreach ($availabilitiesData as $availabilityData) {

            $availabilityId = (string) Str::ulid();

            $availabilitiesToInsert[] = [
                'id'                 => $availabilityId,
                'listing_variant_id' => $variant->id,
                'available_date'     => $availabilityData['available_date'],
                'is_blocked'         => $availabilityData['is_blocked'] ?? false,
                'deleted_at'         => null,   // ← SoftDeletes
                'created_at'         => now(),
                'updated_at'         => now(),
            ];

            foreach ($availabilityData['slots'] ?? [] as $slotData) {
                $slotsToInsert[] = [
                    'id'                      => (string) Str::ulid(),
                    'listing_availability_id' => $availabilityId,
                    'slot_name'               => isset($slotData['slot_name'])
                                                    ? json_encode($slotData['slot_name'])
                                                    : null,
                    'start_time'              => $slotData['start_time'],
                    'end_time'                => $slotData['end_time'],
                    'remaining_capacity'      => $slotData['remaining_capacity'] ?? 1,
                    'deleted_at'              => null,   // ← SoftDeletes
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ];
            }
        }

        if (!empty($availabilitiesToInsert)) {
            DB::table('listing_availabilities')->insert($availabilitiesToInsert);
        }

        if (!empty($slotsToInsert)) {
            DB::table('listing_slots')->insert($slotsToInsert);
        }
    }

  
    private function syncAvailabilities($variant, array $availabilitiesData): void
    {
        $sentAvailabilityIds = collect($availabilitiesData)
            ->pluck('id')
            ->filter()
            ->values()
            ->toArray();

      
        $toDelete = $variant->availabilities()
            ->whereNotIn('id', $sentAvailabilityIds)
            ->get();

        if ($toDelete->isNotEmpty()) {
            $this->assertAvailabilitiesNotBooked($toDelete->pluck('id')->toArray());

            $variant->availabilities()
                ->whereIn('id', $toDelete->pluck('id'))
                ->delete();
        }

        foreach ($availabilitiesData as $availabilityData) {

            $availabilityPayload = [
                'available_date' => $availabilityData['available_date'],
                'is_blocked'     => $availabilityData['is_blocked'] ?? false,
            ];

            if (!empty($availabilityData['id'])) {
                $availability = $variant->availabilities()->findOrFail($availabilityData['id']);
                $availability->update($availabilityPayload);
            } else {
                $availability = $variant->availabilities()->create($availabilityPayload);
            }

            if (!isset($availabilityData['slots'])) continue;

            $this->syncSlots($availability, $availabilityData['slots']);
        }
    }

 private function syncSlots($availability, array $slotsData): void
{
    $slotsCollection = collect($slotsData);

    $slotsWithId    = $slotsCollection->filter(fn($s) => !empty($s['id']))->values();
    $slotsWithoutId = $slotsCollection->filter(fn($s) =>  empty($s['id']))->values();

    $sentExistingIds = $slotsWithId->pluck('id')->toArray();

   
    $slotsToDelete = $availability->slots()
        ->whereNotIn('id', $sentExistingIds)
        ->get();

    if ($slotsToDelete->isNotEmpty()) {
        $this->assertSlotsNotBooked($slotsToDelete->pluck('id')->toArray());

        $availability->slots()
            ->whereIn('id', $slotsToDelete->pluck('id'))
            ->delete();
    }

    $activeSlots = $availability->slots()
        ->get(['id', 'start_time', 'end_time']);

    foreach ($slotsWithId as $slotData) {

        $otherSlots = $activeSlots->filter(fn($s) => $s->id !== $slotData['id']);
        $this->assertNoOverlap($otherSlots, $slotData);

        $slot = $availability->slots()->findOrFail($slotData['id']);
        $slot->update([
            // 🌟 الحل هنا: مرر المصفوفة مباشرة بدون json_encode يدوياً لمنع تدمير النص
            'slot_name'          => $slotData['slot_name'] ?? null,
            'start_time'         => $slotData['start_time'],
            'end_time'           => $slotData['end_time'],
            'remaining_capacity' => $slotData['remaining_capacity'] ?? 1,
        ]);

        $activeSlots = $activeSlots->map(function ($s) use ($slotData) {
            if ($s->id === $slotData['id']) {
                $s->start_time = $slotData['start_time'];
                $s->end_time   = $slotData['end_time'];
            }
            return $s;
        });
    }

    foreach ($slotsWithoutId as $slotData) {
        $this->assertNoOverlap($activeSlots, $slotData);

        $newSlot = $availability->slots()->create([
            'slot_name'          => $slotData['slot_name'] ?? null,
            'start_time'         => $slotData['start_time'],
            'end_time'           => $slotData['end_time'],
            'remaining_capacity' => $slotData['remaining_capacity'] ?? 1,
        ]);

        $activeSlots->push((object)[
            'id'         => $newSlot->id,
            'start_time' => $newSlot->start_time,
            'end_time'   => $newSlot->end_time,
        ]);
    }
}
   

    private function assertAvailabilitiesNotBooked(array $availabilityIds): void
    {
        if (empty($availabilityIds)) return;

        $slotIds = DB::table('listing_slots')
            ->whereIn('listing_availability_id', $availabilityIds)
            ->whereNull('deleted_at') 
            ->pluck('id')
            ->toArray();

        if (!empty($slotIds)) {
            $this->assertSlotsNotBooked($slotIds);
        }
    }

    private function assertSlotsNotBooked(array $slotIds): void
    {

        return;

        if (empty($slotIds)) return;

        $hasActiveOrders = DB::table('orders')
            ->whereIn('listing_slot_id', $slotIds)
            ->whereIn('status', ['pending', 'accepted', 'confirmed'])
            ->exists();

        if ($hasActiveOrders) {
            throw new Exception('لا يمكن تعديل أو حذف الساعات المختارة لوجود حجوزات مؤكدة.');
        }
    }
private function assertNoOverlap($existingSlots, array $newSlot): void
{
    $newStart = date('H:i', strtotime($newSlot['start_time']));
    $newEnd   = date('H:i', strtotime($newSlot['end_time']));

    foreach ($existingSlots as $existSlot) {
        $existStartRaw = is_object($existSlot) ? $existSlot->start_time : $existSlot['start_time'];
        $existEndRaw   = is_object($existSlot) ? $existSlot->end_time : $existSlot['end_time'];

        $existStart = date('H:i', strtotime($existStartRaw));
        $existEnd   = date('H:i', strtotime($existEndRaw));

        if ($newStart < $existEnd && $newEnd > $existStart) {
            throw new \Exception(
                "خطأ في الجدولة: الوقت ({$newStart} - {$newEnd}) يتداخل مع شيفت موجود ({$existStart} - {$existEnd})."
            );
        }
    }
}  }
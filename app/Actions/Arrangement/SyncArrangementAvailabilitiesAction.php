<?php

namespace App\Actions\Arrangement;

use App\Actions\Listing\BulkInsertSlotsAction;
use App\Models\ListingVariant;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * مزامنة تواريخ/أوقات الباقة (Arrangement) بنفس منطق
 * SyncVariantAvailabilitiesAction الخاص بالـ Listing:
 * - مزامنة بالـ ID (إضافة/تعديل/حذف) بدل الحذف والإعادة الكاملة.
 * - حماية التواريخ والـ Slots التي عليها حجوزات نشطة من الحذف.
 * - منع تكرار نفس التاريخ لنفس الـ Variant.
 */
class SyncArrangementAvailabilitiesAction
{
    public function __construct(private BulkInsertSlotsAction $bulkInsertSlotsAction) {}

    /**
     * مزامنة قائمة تواريخ محدَّدة (كل عنصر قد يحمل id للحفاظ عليه).
     */
    public function execute(ListingVariant $variant, array $availabilitiesData, int $defaultCapacity = 1): void
    {
        // قفل صف الـ variant لمنع التعارض عند التحديث المتزامن
        $variant = ListingVariant::lockForUpdate()->findOrFail($variant->id);

        // استخدام السعة الخاصة بالـ variant كقيمة أساسية للـ slots إذا لم تُمرر
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

            // تعديل ديناميكي لضمان أن الـ remaining_capacity يطابق سعة الباقة الحقيقية
            // ולא يتم الاعتماد على أرقام قديمة أو سالبة عند إعادة مزامنة أو إنشاء المواعيد
            $slots = collect($availabilityData['slots'] ?? [])
                ->map(function ($slot) use ($baseCapacity) {
                    // إذا لم يتم إرسال remaining_capacity أو كانت سالبة، يتم ضبطها على السعة الأساسية للباقة
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

    /**
     * تحويل نطاق زمني (date_range) إلى مصفوفة تواريخ فردية.
     */
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
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms a Listing (package type) into a clean API response.
 *
 * Expects eager-loaded relations:
 * ->load(['variants.packageItems.includedVariant.listing', 'variants.packageFreelancers.freelancer', 'variants.availabilities.slots', 'images', 'category', 'district'])
 */
class ArrangementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // A package always has exactly one variant that acts as the price container.
        $variant = $this->variants->first();

        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'description'  => $this->description,
            'listing_type' => $this->listing_type, // always "package"

            'category' => $this->whenLoaded('category', function () {
                return [
                    'id'      => $this->category->id,
                    'name_en' => $this->category->name_en ?? $this->category->name,
                    'name_ar' => $this->category->name_ar ?? $this->category->name,
                ];
            }),
            'district' => $this->whenLoaded('district', function () {
                return [
                    'id'      => $this->district->id,
                    'name_en' => $this->district->name_en ?? $this->district->name,
                    'name_ar' => $this->district->name_ar ?? $this->district->name,
                ];
            }),

            'moderation_status' => $this->moderation_status,

            // ── Pricing (from the single variant) ───────────────────────────
            'variant_id' => $variant?->id,
            'price'      => $variant ? (float) $variant->price : null,
            'price_type' => $variant?->price_type,
            'currency'   => $variant?->currency,
            'capacity'   => $variant?->dynamic_attributes['capacity'] ?? null,


            // ── Package items (products / halls / services) ──────────────────
            'items' => $this->whenloaded('variants', function () use ($variant): array {
                if (! $variant) {
                    return [];
                }

                return $variant->packageitems
                    ->map(function ($item) {
                        // سحب صورة النسخة (اللون) مباشرة عبر الـ accessor full_url
                        $incVar = $item->includedVariant ?? $item->includedvariant;
                        $variantimageurl = null;
                        
                        if ($incVar) {
                            // 1. البحث بقوة في صور النسخة (اللون)
                            if ($incVar->images && $incVar->images->isNotEmpty()) {
                                $img = $incVar->images->first();
                                // التقاط الرابط أياً كان اسمه في قاعدة البيانات
                                $variantimageurl = $img->full_url ?? $img->original_url ?? $img->url ?? $img->path;
                            } 
                            // 2. إذا لم يجدها، يبحث بقوة في صور المنتج الأساسي كبديل
                            elseif ($incVar->listing && $incVar->listing->images && $incVar->listing->images->isNotEmpty()) {
                                $img = $incVar->listing->images->first();
                                // التقاط الرابط أياً كان اسمه
                                $variantimageurl = $img->full_url ?? $img->original_url ?? $img->url ?? $img->path;
                            }
                        }
                        return [
                            'id'         => $item->id,
                            'quantity'   => $item->quantity,

                            'variant' => $item->includedvariant ? [
                                'id'           => $item->includedvariant->id,
                                'variant_name' => $item->includedvariant->variant_name,
                                'price'        => (float) $item->includedvariant->price,
                                'currency'     => $item->includedvariant->currency,
                                'image'        => $variantimageurl,
                                'listing'      => $item->includedvariant->listing ? [
                                    'id'           => $item->includedvariant->listing->id,
                                    'title'        => $item->includedvariant->listing->title,
                                    'listing_type' => $item->includedvariant->listing->listing_type,
                                ] : null,
                            ] : null,
                        ];
                    })
                    ->values()
                    ->toarray();
            }),

            // ── Contract-linked freelancers ───────────────────────────────────
            'freelancers' => $this->whenLoaded('variants', function () use ($variant): array {
                if (! $variant) {
                    return [];
                }

                return $variant->packageFreelancers
                    ->map(fn($pf) => [
                        'id'            => $pf->id,
                        'freelancer_id' => $pf->freelancer_id,
                        'contract_id'   => $pf->contract_id,
                        'freelancer'    => $pf->freelancer ? [
                            'id'         => $pf->freelancer->id,
                            'brand_name' => $pf->freelancer->brand_name,
                        ] : null,
                        'service' => $pf->contract?->jobOffer?->service ? [
                            'id'          => $pf->contract->jobOffer->service->id,
                            'name'        => $pf->contract->jobOffer->service->name,
                            'description' => $pf->contract->jobOffer->service->description,
                        ] : null,
                    ])
                    ->values()
                    ->toArray();
            }),


            // ── Images ───────────────────────────────────────────────────────
            'images' => $this->relationLoaded('images')
                ? $this->images->map(fn($img) => [
                    'id'  => $img->id,
                    'url' => $img->full_url,
                    'alt' => $img->alt_text,
                ])->values()->toArray()
                : [],

            // ── Availabilities (نفس تنسيق ListingResource تماماً) ─────────────
            'availabilities' => $variant && $variant->relationLoaded('availabilities')
                ? $variant->availabilities->map(fn($availability) => [
                    'id'             => $availability->id,
                    'available_date' => $availability->available_date,
                    'is_blocked'     => (bool) $availability->is_blocked,
                    'slots' => $availability->relationLoaded('slots')
                        ? $availability->slots->map(fn($slot) => [
                            'id'                 => $slot->id,
                            'name'               => $slot->slot_name,
                            'start_time'         => $slot->start_time,
                            'end_time'           => $slot->end_time,
                            'remaining_capacity' => (int) $slot->remaining_capacity,
                        ])->values()->toArray()
                        : [],
                ])->values()->toArray()
                : [],

            // ── Cancellation policies ─────────────────────────────────────────
            'cancel_policies' => [
                'before_acceptance' => (bool) $this->cancel_before_acceptance,
                'after_acceptance'  => (bool) $this->cancel_after_acceptance,
                'before_payment'    => (bool) $this->cancel_before_payment,
            ],

            'secondary_contact_number' => $this->secondary_contact_number,
            'created_at'               => $this->created_at?->toISOString(),
            'updated_at'               => $this->updated_at?->toISOString(),
        ];
    }
}

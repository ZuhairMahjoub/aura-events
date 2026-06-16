<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms a Listing (package type) into a clean API response.
 *
 * Expects eager-loaded relations:
 * ->load(['variants.packageItems.includedVariant.listing', 'variants.packageFreelancers.freelancer', 'images', 'category', 'district'])
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

            'category' => $this->whenLoaded('category', fn () => [
                'id'   => $this->category->id,
                'name' => $this->category->name ?? null,
            ]),

            'district' => $this->whenLoaded('district', fn () => [
                'id'   => $this->district->id,
                'name' => $this->district->name ?? null,
            ]),

            'moderation_status' => $this->moderation_status,

            // ── Pricing (from the single variant) ───────────────────────────
            'variant_id' => $variant?->id,
            'price'      => $variant ? (float) $variant->price : null,
            'price_type' => $variant?->price_type,
            'currency'   => $variant?->currency,
            'capacity'   => $variant?->dynamic_attributes['capacity'] ?? null,

            // ── Package items (products / halls / services) ──────────────────
            'items' => $this->whenLoaded('variants', function () use ($variant): array {
                if (! $variant) {
                    return [];
                }

                return $variant->packageItems
                    ->map(fn ($item) => [
                        'id'         => $item->id,
                        'variant_id' => $item->included_variant_id,
                        'quantity'   => $item->quantity,

                        'variant' => $item->includedVariant ? [
                            'id'           => $item->includedVariant->id,
                            'variant_name' => $item->includedVariant->variant_name,
                            'price'        => (float) $item->includedVariant->price,
                            'currency'     => $item->includedVariant->currency,
                            'listing'      => $item->includedVariant->listing ? [
                                'id'           => $item->includedVariant->listing->id,
                                'title'        => $item->includedVariant->listing->title,
                                'listing_type' => $item->includedVariant->listing->listing_type,
                            ] : null,
                        ] : null,
                    ])
                    ->values()
                    ->toArray();
            }),

            // ── Contract-linked freelancers ───────────────────────────────────
            'freelancers' => $this->whenLoaded('variants', function () use ($variant): array {
                if (! $variant) {
                    return [];
                }

                return $variant->packageFreelancers
                    ->map(fn ($pf) => [
                        'id'            => $pf->id,
                        'freelancer_id' => $pf->freelancer_id,
                        'contract_id'   => $pf->contract_id,
                        'freelancer'    => $pf->freelancer ? [
                            'id'         => $pf->freelancer->id,
                            'brand_name' => $pf->freelancer->brand_name,
                        ] : null,
                    ])
                    ->values()
                    ->toArray();
            }),

            // ── Images ───────────────────────────────────────────────────────
            'images' => $this->relationLoaded('images')
                ? $this->images->map(fn($img) => [
                    'id'  => $img->id,
                    'url' => asset($img->path), // التعديل الآمن: يجلب الرابط كاملاً بالدومين المحلي أو الحقيقي للملف
                    'alt' => $img->alt_text
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
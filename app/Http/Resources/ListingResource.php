<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                         => $this->id,
            'title'                      => $this->title,
            'description'                => $this->description,
            'type'                       => $this->listing_type,
            'status'                     => $this->moderation_status,
            'material_composition'       => $this->material_composition,
            'secondary_contact_number'   => $this->secondary_contact_number,
            'cancel_before_acceptance'   => (bool) $this->cancel_before_acceptance,
            'cancel_after_acceptance'    => (bool) $this->cancel_after_acceptance,
            'cancel_before_payment'      => (bool) $this->cancel_before_payment,
            'is_provider_location_based' => (bool) $this->is_provider_location_based,
            'rejection_reason'           => $this->rejection_reason,

            'category' => $this->relationLoaded('category') && $this->category
                ? ['id' => $this->category->id, 'name' => $this->category->name]
                : null,

            'district' => $this->relationLoaded('district') && $this->district
                ? ['id' => $this->district->id, 'name' => $this->district->name]
                : null,

            'provider' => $this->relationLoaded('provider') && $this->provider
                ? [
                    'id'            => $this->provider->id,
                    'type'          => $this->provider->provider_type, // 'company' أو 'freelancer'
                    'name'          => $this->provider->provider_type === 'company'
                        ? ($this->provider->brand_name ?? trim($this->provider->user->first_name . ' ' . $this->provider->user->last_name))
                        : trim($this->provider->user->first_name . ' ' . $this->provider->user->last_name),
                    'email'         => $this->provider->user->email,
                    'is_verified'   => (bool) $this->provider->is_verified,
                ]
                : null,
            'images' => $this->relationLoaded('images')
                ? $this->images->map(fn($img) => [
                    'id'  => $img->id,
                    'url' => $img->full_url,
                    'alt' => $img->alt_text,
                ])
                : [],

            'variants' => $this->relationLoaded('variants')
                ? $this->variants->map(fn($variant) => [
                    'id'         => $variant->id,
                    'name'       => $variant->variant_name,
                    'price'      => (float) $variant->price,
                    'currency'   => $variant->currency,
                    'price_type' => $variant->price_type,
                    'stock'      => $variant->stock_quantity,
                    'attributes' => $variant->dynamic_attributes,
                    'capacity'   => $variant->capacity,
                    'images' => $variant->relationLoaded('images')
                        ? $variant->images->map(fn($img) => [
                            'id'  => $img->id,
                            'url' => $img->full_url,
                            'alt' => $img->alt_text,
                        ])
                        : [],

                    'availabilities' => $variant->relationLoaded('availabilities')
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
                                ])
                                : [],
                        ])
                        : [],

                    'package_items' => $variant->relationLoaded('packageItems')
                        ? $variant->packageItems->map(fn($item) => [
                            'id'               => $item->id,
                            'quantity'         => $item->quantity ?? null,
                            'included_variant' => $item->relationLoaded('includedVariant') && $item->includedVariant
                                ? [
                                    'id'      => $item->includedVariant->id,
                                    'name'    => $item->includedVariant->variant_name,
                                    'listing' => $item->includedVariant->relationLoaded('listing') && $item->includedVariant->listing
                                        ? ['id' => $item->includedVariant->listing->id, 'title' => $item->includedVariant->listing->title]
                                        : null,
                                ]
                                : null,
                        ])
                        : [],

                    'package_freelancers' => $variant->relationLoaded('packageFreelancers')
                        ? $variant->packageFreelancers->map(fn($pf) => [
                            'id'         => $pf->id,
                            'freelancer' => $pf->relationLoaded('freelancer') && $pf->freelancer
                                ? [
                                    'id'    => $pf->freelancer->id,
                                    'name'  => trim($pf->freelancer->user->first_name . ' ' . $pf->freelancer->user->last_name),
                                    'email' => $pf->freelancer->user->email,
                                ]
                                : null,
                        ])
                        : [],
                ])
                : [],

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
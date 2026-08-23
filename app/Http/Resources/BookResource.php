<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\ListingAvailability;

class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $listing     = $this->listing;
        $variant     = $this->variant;
        $listingType = $listing->listing_type ?? $this->booking_type ?? 'unknown';

        return [
            'id'       => $this->id,
            'status'   => $this->status ?? 'unknown',

            'price'    => $this->total_price ?? ($variant->price ?? 0),
            'currency' => $variant->currency ?? 'USD',

            'day_schedule' => $this->buildDaySchedule($variant),

            'provider_id' => $this->provider_id,
            'booked_date' => $this->booked_date,
            'quantity'    => $this->quantity ?? 1,
            
            // 🚀 التعديلات المطلوبة للرياكت
            'metadata'    => $this->metadata,
'payment_id' => $this->relationLoaded('payments') ? $this->payments->last()?->id : null,
            'customer' => $this->whenLoaded('user', fn () => $this->user ? [
                'id'             => $this->user->id,
                'name'           => trim(($this->user->first_name ?? '') . ' ' . ($this->user->last_name ?? '')) ?: 'غير معروف',
                'first_name'     => $this->user->first_name ?? '',
                'last_name'      => $this->user->last_name ?? '',
                'phone'          => $this->user->phone ?? '',
                'email'          => $this->user->email ?? '',
                'status'         => $this->user->status ?? 'unknown',
            ] : null, [
                'id' => null, 'name' => 'غير محمّل', 'first_name' => '', 'last_name' => '',
                'phone' => '', 'phone_verified' => false, 'email' => '', 'email_verified' => false,
            ]),

            'listing' => $listing ? $this->buildListingDetails($listing, $variant, $listingType) : null,

            'created_at_human' => $this->created_at?->diffForHumans() ?? '',
        ];
    }

    protected function buildDaySchedule($variant): array
    {
        if (!$variant || !$this->booked_date) {
            return [
                'date'   => $this->booked_date,
                'shifts' => [],
            ];
        }

        $bookedSlotId = $this->listing_slot_id;

        $availability = ListingAvailability::query()
            ->where('listing_variant_id', $variant->id)
            ->whereDate('available_date', $this->booked_date)
            ->with('slots')
            ->first();

        $shifts = $availability
            ? $availability->slots->map(fn ($slot) => [
                'id'                 => $slot->id,
                'name'               => $slot->slot_name,
                'start_time'         => $slot->start_time,
                'end_time'           => $slot->end_time,
                'remaining_capacity' => $slot->remaining_capacity,
                'is_booked'          => $slot->id === $bookedSlotId,
            ])->values()->all()
            : [];

        return [
            'date'       => $this->booked_date,
            'is_blocked' => $availability->is_blocked ?? false,
            'shifts'     => $shifts,
        ];
    }

    protected function buildListingDetails($listing, $variant, ?string $listingType): array
    {
        return [
            'id'                          => $listing->id,
            'title'                       => $listing->title,
            'description'                 => $listing->description,
            'listing_type'                => $listingType,
            'category_id'                 => $listing->category_id,
            'district_id'                 => $listing->district_id,
            'material_composition'        => $listingType === 'physical_product' ? $listing->material_composition : null,
            'is_provider_location_based'  => $listing->is_provider_location_based,
            'secondary_contact_number'    => $listing->secondary_contact_number,

            'images' => $listing->relationLoaded('images')
                ? $listing->images->map(fn ($img) => [
                    'id'  => $img->id,
                    'url' => $img->full_url,
                    'alt' => $img->alt_text,
                ])->values()->all()
                : [],

            'provider' => $listing->relationLoaded('provider') && $listing->provider ? [
                'id'          => $listing->provider->id,
                'brand_name'  => $listing->provider->brand_name,
                'rating'      => $listing->provider->rating,
                'is_verified' => $listing->provider->is_verified,
            ] : null,

            'variant' => $variant ? $this->buildVariantDetails($variant, $listingType) : null,
        ];
    }

   protected function buildVariantDetails($variant, ?string $listingType): array
    {
        $base = [
            'id'                 => $variant->id,
            'variant_name'       => $variant->variant_name,
            'price'              => $variant->price,
            'currency'           => $variant->currency,
            'price_type'         => $variant->price_type,
            'dynamic_attributes' => $variant->dynamic_attributes,
        ];

        // إذا لم يكن باقة، نرجع البيانات العادية
        if ($listingType !== 'package') {
            return match ($listingType) {
                'hall', 'service' => $base + ['capacity' => $variant->capacity],
                'physical_product' => $base + ['stock_quantity' => $variant->stock_quantity],
                default => $base,
            };
        }

        // 🚀 معالجة الباقة (Package) وإجبار لارافيل على جلب صور المنتجات الفرعية
        $items = [];
       if ($variant->relationLoaded('packageItems')) {
            $items = $variant->packageItems->map(function ($item) {
                // 🚀 استعلام مباشر لضمان جلب صورة المنتج المضمن (الكرسي) وليس التنسيق!
                $productListing = $item->includedVariant?->listing;
                $imageUrl = null;
                
                if ($productListing) {
                    $image = $productListing->images()->first();
                    $imageUrl = $image ? $image->full_url : null;
                }

                return [
                    'id'                  => $item->id,
                    'included_variant_id' => $item->included_variant_id,
                    'item_name'           => $item->includedVariant->variant_name ?? null,
                    'image'               => $imageUrl, // 🚀 سيضع رابط صورة الكرسي هنا
                    'quantity'            => $item->quantity,
                    'metadata'            => $item->metadata,
                ];
            })->values()->all();
        }

        $freelancers = [];
        if ($variant->relationLoaded('packageFreelancers')) {
            $freelancers = $variant->packageFreelancers->map(fn ($f) => [
                'id'            => $f->id,
                'freelancer_id' => $f->freelancer_id,
                'name'          => $f->freelancer->brand_name ?? null,
                'contract_id'   => $f->contract_id,
            ])->values()->all();
        }

        return $base + [
            'capacity'    => $variant->capacity,
            'items'       => $items,
            'freelancers' => $freelancers,
        ];
    }
    protected function buildOrderDetails(?string $listingType): ?array
    {
        $metadata = $this->metadata ?? [];

        return match ($listingType) {
            'hall', 'service' => [
                'event_type'  => $metadata['event_type'] ?? null,
                'guest_count' => $metadata['guest_count'] ?? null,
                'setup_needs' => $metadata['setup_needs'] ?? null,
            ],
            'physical_product' => [
                'is_rental'        => $metadata['is_rental'] ?? false,
                'rental_days'      => $metadata['rental_days'] ?? null,
                'delivery_address' => $metadata['delivery_address'] ?? null,
            ],
            'package' => [
                'is_coordination_package' => $metadata['is_coordination_package'] ?? true,
                'booking_items'           => $metadata['booking_items'] ?? [],
            ],
            default => $metadata ?: null,
        };
    }
}
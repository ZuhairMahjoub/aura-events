<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\ListingAvailability;

class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // نخزّن العلاقات بمتغيرات محلية مرة وحدة، بدل ما نستدعي
        // $this->listing / $this->variant عدة مرات (كل استدعاء لـ magic
        // property بيعمل property_exists + method_exists check إضافي).
        $listing     = $this->listing;
        $variant     = $this->variant;
        $listingType = $listing->listing_type ?? $this->booking_type ?? 'unknown';

        return [
            'id'       => $this->id,
            'status'   => $this->status ?? 'unknown',

            'price'    => $this->total_price ?? ($variant->price ?? 0),
            'currency' => $variant->currency ?? 'USD',

            // جدول اليوم المحجوز فيه بالكامل: التاريخ + كل الـ shifts
            // (slots) المتاحة لنفس الـ variant بنفس اليوم، مع تحديد أيهم
            // هو الـ shift المحجوز فعلياً بهذا الحجز (is_booked).
            'day_schedule' => $this->buildDaySchedule($variant),

            'provider_id' => $this->provider_id,
            'booked_date' => $this->booked_date,
            'quantity'    => $this->quantity ?? 1,

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

            // العرض المحجوز بالكامل (Listing + Variant)، مفلتر حسب النوع.
            'listing' => $listing ? $this->buildListingDetails($listing, $variant, $listingType) : null,

            // ما طلبه الزبون فعلياً وقت الحجز (event_type, guest_count, delivery_address...)

            'created_at_human' => $this->created_at?->diffForHumans() ?? '',
        ];
    }

    /**
     * يبني جدول اليوم كامل: التاريخ المحجوز (booked_date) مع كل الـ shifts
     * (ListingSlot) المتاحة لنفس اليوم على نفس الـ variant، عبر
     * ListingAvailability (variant_id + available_date) → slots.
     * كل shift بيترجع مع remaining_capacity وعلامة is_booked توضح إذا
     * هو الشيفت المحجوز فعلياً بهذا السجل أو مجرد شيفت متاح آخر بنفس اليوم.
     */
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
            // material_composition: حقل خاص بـ physical_product فقط حسب StoreListingRequest
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

    /**
     * تفاصيل الـ variant، مبنية حصراً على ما يفرضه StoreListingRequest فعلياً
     * لكل نوع listing_type وقت الرفع:
     * - physical_product: stock_quantity إلزامي، capacity غير مستخدم
     * - service/hall/package: capacity اختياري، stock_quantity غير مستخدم
     *
     * ملاحظة: dynamic_attributes موجود كعمود بقاعدة البيانات لكنه غير
     * مستخدم إطلاقاً في StoreListingRequest/ListingController حالياً (لا
     * يوجد حقل color أو أي خاصية إضافية يتم إرسالها أو حفظها وقت رفع
     * العرض). نعرضه هنا فقط لأنه العمود الوحيد المتاح لهذا الغرض، وسيبقى
     * null دائماً حتى تتم إضافته فعلياً لمسار الرفع.
     */
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

        return match ($listingType) {
            'hall', 'service', 'package' => $base + ['capacity' => $variant->capacity],

            'physical_product' => $base + ['stock_quantity' => $variant->stock_quantity],

            default => $base,
        } + ($listingType === 'package' ? [
            'items' => $variant->relationLoaded('packageItems')
                ? $variant->packageItems->map(fn ($item) => [
                    'id'                  => $item->id,
                    'included_variant_id' => $item->included_variant_id,
                    'item_name'           => $item->includedVariant->variant_name ?? null,
                    'quantity'            => $item->quantity,
                    'metadata'            => $item->metadata,
                ])->values()->all()
                : [],

            'freelancers' => $variant->relationLoaded('packageFreelancers')
                ? $variant->packageFreelancers->map(fn ($f) => [
                    'id'            => $f->id,
                    'freelancer_id' => $f->freelancer_id,
                    'name'          => $f->freelancer->brand_name ?? null,
                    'contract_id'   => $f->contract_id,
                ])->values()->all()
                : [],
        ] : []);
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
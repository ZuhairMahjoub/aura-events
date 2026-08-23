<?php
namespace App\DTOs;

readonly class BookingData
{
    public function __construct(
        public string  $userId,
        public string  $listingId,
        public string  $variantId,
        public ?string $slotId,
        public ?string $bookedDate,
        public int     $quantity = 1,
        public array   $metadata = [],
        public ?string $customerNotes = null,
    ) {}

    public static function fromRequest(array $validated, string $userId): self
    {
        $metadata = $validated['metadata'] ?? [];
        if (isset($validated['custom_items'])) {
            $metadata['custom_items'] = $validated['custom_items'];
        }
if (isset($validated['custom_freelancers'])) {
    $metadata['custom_freelancers'] = $validated['custom_freelancers'];
}
        return new self(
            userId:         $userId,
            listingId:      $validated['listing_id'],
            variantId:      $validated['listing_variant_id'],
            slotId:         $validated['listing_slot_id'] ?? null,
            bookedDate:     $validated['booked_date'] ?? null,
            quantity:       $validated['quantity'] ?? 1,
            metadata:       $metadata,
            customerNotes:  $validated['customer_notes'] ?? null,
        );
    }
}
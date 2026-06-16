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
        return new self(
            userId:         $userId,
            listingId:      $validated['listing_id'],
            variantId:      $validated['listing_variant_id'],
            slotId:         $validated['listing_slot_id'] ?? null,
            bookedDate:     $validated['booked_date'] ?? null,
            quantity:       $validated['quantity'] ?? 1,
            metadata:       $validated['metadata'] ?? [],
            customerNotes:  $validated['customer_notes'] ?? null,
        );
    }
}
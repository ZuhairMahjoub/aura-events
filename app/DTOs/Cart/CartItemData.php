<?php

namespace App\DTOs\Cart;

readonly class CartItemData
{
    public function __construct(
        public string  $variantId,
        public ?string $slotId,
        public int     $quantity,
        public ?string $bookedDate,
        public array   $metadata = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            variantId:  $data['listing_variant_id'],
            slotId:     $data['listing_slot_id'] ?? null,
            quantity:   $data['quantity'] ?? 1,
            bookedDate: $data['booked_date'] ?? null,
            metadata:   $data['metadata'] ?? []
        );
    }
}

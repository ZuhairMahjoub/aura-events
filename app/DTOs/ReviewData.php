<?php

namespace App\DTOs;

readonly class ReviewData
{
    public function __construct(
        public string $bookingId,
        public int    $rating,
        public ?string $comment = null,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            bookingId: $validated['booking_id'],
            rating:    $validated['rating'],
            comment:   $validated['comment'] ?? null,
        );
    }
}
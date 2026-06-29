<?php

namespace App\Http\Resources\Cart;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'price_at_addition' => $this->price_snapshot,
            'total_item_price' => (float) $this->price_snapshot * $this->quantity,
            'booked_date' => $this->booked_date?->toDateString(),
            'listing' => [
                'id' => $this->listing->id,
                'title' => $this->listing->title, // يدعم الترجمة إذا كان JSON
            ],
            'variant' => [
                'id' => $this->variant->id,
                'name' => $this->variant->variant_name,
            ],
            'slot' => $this->when($this->listing_slot_id, fn() => [
                'id' => $this->slot->id,
                'time' => $this->slot->start_time . ' - ' . $this->slot->end_time,
            ]),
        ];
    }
}

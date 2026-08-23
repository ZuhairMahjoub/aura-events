<?php

namespace App\Http\Resources\Cart;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cart_id' => $this->id,
            'status' => $this->status,
            'items_count' => $this->items->count(),
            'total_price' => $this->total_price,
            'items' => CartItemResource::collection($this->items),
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
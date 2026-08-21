<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'status'       => $this->status,

            'price'        => $this->total_price ?? ($this->variant->price ?? 0),
            'currency'     => $this->variant->currency ?? 'USD',

            'shift'        => $this->relationLoaded('slot') && $this->slot ? [
                'id'         => $this->slot->id,
                'name'       => $this->slot->slot_name,
                'start_time' => $this->slot->start_time,
                'end_time'   => $this->slot->end_time,
            ] : null,

            'provider_id'  => $this->provider_id,
            'booked_date'  => $this->booked_date,
            'listing'      => [
                'id'           => $this->listing->id ?? null,
                'title'        => $this->listing->title ?? null,
                'listing_type' => $this->listing->listing_type ?? null, // صالة، تنسيق، منتج
            ],

            'payment' => $this->relationLoaded('payments') && $this->payments->isNotEmpty() ? [
                'payment_status' => $this->payments->last()->status,
            ] : null,
            'variant'      => [
                'id'   => $this->variant->id ?? null,
                'name' => $this->variant->variant_name ?? null,
            ],

            'customer'     => $this->relationLoaded('user') && $this->user ? [
                'id'    => $this->user->id,
                'name'  => trim(($this->user->first_name ?? '') . ' ' . ($this->user->last_name ?? '')),
                'phone' => $this->user->phone,
            ] : null,

            'created_at_human' => $this->created_at ? $this->created_at->diffForHumans() : null,
        ];
    }
}

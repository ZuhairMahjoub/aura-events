<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $freelancers = [];
        if (is_array($this->metadata) && isset($this->metadata['booking_items'])) {
            foreach ($this->metadata['booking_items'] as $item) {
                if (isset($item['type']) && $item['type'] === 'freelancer') {
                    $freelancers[] = $item['name'] ?? 'مستقل';
                }
            }
        }
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
'payment_id' => $this->relationLoaded('payments') ? $this->payments->last()?->id : null,
            'booked_start_time' => $this->booked_start_time,
            'booked_end_time'   => $this->booked_end_time,
            'quantity'          => $this->quantity,
            'metadata'          => $this->metadata,
'freelancers'       => $freelancers, 
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
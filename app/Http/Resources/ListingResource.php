<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingResource extends JsonResource
{
  public function toArray(Request $request): array
{
    return [
        'id'           => $this->id,
        'title'        => $this->title, 
        'description'  => $this->description,
        'type'         => $this->listing_type,
        'status'       => $this->moderation_status,
        
        'images'       => $this->relationLoaded('images') 
            ? $this->images->map(fn($img) => ['url' => $img->url, 'alt' => $img->alt_text]) 
            : [],

        'variants'     => $this->relationLoaded('variants') 
            ? $this->variants->map(fn($variant) => [
                'id'         => $variant->id,
                'name'       => $variant->variant_name,
                'price'      => (float) $variant->price,
                'currency'   => $variant->currency,
                
                'images'     => $variant->relationLoaded('images')
                    ? $variant->images->map(fn($img) => ['url' => $img->url, 'alt' => $img->alt_text])
                    : [],

                'stock'      => $variant->stock_quantity,
                'attributes' => $variant->dynamic_attributes,
                
            ])
            : [],
        
        'created_at'   => $this->created_at?->toIso8601String(),
    ];
}
}
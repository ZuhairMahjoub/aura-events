<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                   => $this->id,
            'first_name'           => $this->first_name,
            'last_name'            => $this->last_name,
            'full_name'            => $this->first_name . ' ' . $this->last_name,
            'email'                => $this->email,
            'phone'                => $this->phone,
            'status'               => $this->status,
            'is_verified'          => !is_null($this->email_verified_at) || !is_null($this->phone_verified_at),
            'settings'             => [
                'language' => $this->settings_language,
                'theme'    => $this->settings_theme,
            ],
            // عرض التواريخ بشكل منظم
            'created_at'           => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        ];
    }
}
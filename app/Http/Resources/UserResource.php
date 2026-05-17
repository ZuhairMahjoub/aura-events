<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'first_name'           => $this->first_name,
            'last_name'            => $this->last_name,
            'email'                => $this->email,
            'phone'                => $this->phone,
            'is_profile_completed' => $this->is_profile_completed,
            'roles'                => $this->getRoleNames(),
            'email_verified'       => !is_null($this->email_verified_at),
            'phone_verified'       => !is_null($this->phone_verified_at),
        ];
    }
}
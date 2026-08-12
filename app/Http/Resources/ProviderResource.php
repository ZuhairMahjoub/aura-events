<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isCompany    = $this->provider_type === 'company';
        $details      = $isCompany ? $this->companyDetails : $this->freelancerDetails;
        $user         = $this->user;

        $district = $isCompany ? $this->companyDetails?->district : null;

        $categories = $this->whenLoaded('categories', function () {
            return $this->categories->map(fn ($cat) =>
                $cat->name_ar ?? $cat->name_en ?? $cat->name ?? null
            )->filter()->values();
        }, collect());

        return [

            'profile' => [
                'id'                  => $this->id,
                'brand_name'          => $this->brand_name,
                'provider_type'       => $this->provider_type,
                'rating'              => (float) $this->rating,
                'is_active'           => (bool) $this->is_active,
                'categories'          => $categories,
                'primary_category'    => $categories->first() ?? 'No Category',

                'verification_badge'  => $this->is_verified ? 'VERIFIED' : 'UNVERIFIED',

                'approval_badge'      => strtoupper($this->moderation_status ?? 'PENDING'),

                'join_date'           => $user?->created_at?->format('Y-m-d'),

                'city'                => $district?->name_ar ?? $district?->name_en ?? null,
                'district_id'         => $isCompany ? $details?->district_id : null,
                'address_details'     => $isCompany ? $details?->address_details : null,
            ],

          
            'identity' => [
                'first_name'          => $user?->first_name,
                'last_name'           => $user?->last_name,
                'full_name'           => $user
                    ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
                    : null,


                'language'            => $user?->settings_language ?? 'ar',
                'theme'               => $user?->settings_theme ?? 'light',

                'email'               => $user?->email,

                'phone'               => $user?->phone,
            ],

           
            'business' => [
                'brand_name'          => $this->brand_name,

                'tax_number'          => $isCompany ? $details?->tax_number        : null,
                'registration_no'     => $isCompany ? $details?->registration_no   : null,

                'national_id'         => !$isCompany ? $details?->national_id       : null,
                'experience_years'    => !$isCompany ? $details?->experience_years  : null,
            ],

            
            'security' => [
                
                'email_verified'      => $user?->email_verified_at
                    ? 'Verified'
                    : 'Not Verified',
                'is_email_verified'   => (bool) $user?->email_verified_at,

                'phone_verified'      => $user?->phone_verified_at
                    ? 'Verified'
                    : 'Not Verified',
                'is_phone_verified'   => (bool) $user?->phone_verified_at,

                'account_status'      => $user?->status ?? 'active',

                'provider_verified'   => $this->is_verified ? 'Verified' : 'Pending',
                'is_verified'         => (bool) $this->is_verified,

                'moderation_status'   => $this->moderation_status,
                
                'is_profile_completed' => (bool) ($user?->is_profile_completed ?? false),
            ],
        ];
    }
}
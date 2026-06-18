<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProviderResource extends JsonResource
{
    public function toArray($request)
    {
        // البيانات الأساسية المشتركة
        $data = [
            'id'            => $this->id,
            'name'          => $this->user->first_name . ' ' . $this->user->last_name,
            'provider_type' => $this->provider_type,
            'brand_name'    => $this->brand_name,
            'categories'    => $this->categories->map(fn($c) => [
                'id' => $c->id, 
                'name' => $c->name
            ]),
        ];

        // إضافة التفاصيل بناءً على النوع
        if ($this->provider_type === 'freelance' && $this->freelancerDetails) {
            $data['details'] = [
                'national_id'      => $this->freelancerDetails->national_id,
                'experience_years' => $this->freelancerDetails->experience_years,
            ];
        } elseif ($this->provider_type === 'company' && $this->companyDetails) {
            $data['details'] = [
                'tax_number'      => $this->companyDetails->tax_number,
                'registration_no' => $this->companyDetails->registration_no,
                'address'         => $this->companyDetails->address_details,
                'district_id'     => $this->companyDetails->district_id,
            ];
        }

        return $data;
    }
}
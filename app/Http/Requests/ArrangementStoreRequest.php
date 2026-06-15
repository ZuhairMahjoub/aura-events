<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArrangementStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $provider = $this->user()->providerProfile;

        if (!$provider) {
            return false;
        }

        return $provider->is_active && $provider->provider_type === 'company';
    }

    public function rules(): array
    {
        return [
            'category_id'              => 'required|exists:categories,id',
            'district_id'              => 'required|exists:districts,id',
            'title'                    => 'required|string|max:255|min:3',
            'description'              => 'required|string|min:10',
            'price'                    => 'required|numeric|min:0|max:999999.99',
            'price_type'               => 'required|in:fixed,per_hour,per_day',
            'capacity'                 => 'required|integer|min:1|max:10000',
            'secondary_contact_number' => 'nullable|string|regex:/^[0-9\-\+\s()]+$/',

            'products'                 => 'nullable|array|max:100',
            'products.*.variant_id'    => 'required|string|exists:listing_variants,id',
            'products.*.quantity'      => 'required|integer|min:1|max:1000',

            'freelancers'              => 'nullable|array|max:50',
            'freelancers.*.freelancer_id' => 'required|string|exists:providers,id',
            'freelancers.*.contract_id'   => 'required|string|exists:company_freelancer_contracts,id',

            'images'                   => 'nullable|array|max:10',
            'images.*'                 => 'string|regex:/^temp\/[a-zA-Z0-9\-_.]+$/',
        ];
    }

    public function messages(): array
    {
        return [
            'price_type.in' => 'نوع السعر يجب أن يكون: fixed, per_hour, أو per_day',
            'title.min' => 'العنوان يجب أن يكون 3 أحرف على الأقل',
            'description.min' => 'الوصف يجب أن يكون 10 أحرف على الأقل',
            'secondary_contact_number.regex' => 'رقم الهاتف غير صحيح',
        ];
    }
}
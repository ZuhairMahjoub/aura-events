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

        // ── District: للشركات يأتي من company_details، للفريلانسر null ──────
        $district = $isCompany ? $this->companyDetails?->district : null;

        // ── Categories ────────────────────────────────────────────────────────
        $categories = $this->whenLoaded('categories', function () {
            return $this->categories->map(fn ($cat) =>
                $cat->name_ar ?? $cat->name_en ?? $cat->name ?? null
            )->filter()->values();
        }, collect());

        return [

            // ═══════════════════════════════════════════════════════════════
            // Section 1: Profile Card (الكارت العلوي)
            // Displays: brand name, type badge, verification badge,
            //           approval badge, join date, location/city
            // ═══════════════════════════════════════════════════════════════
            'profile' => [
                'id'                  => $this->id,
                'brand_name'          => $this->brand_name,
                'provider_type'       => $this->provider_type,
                'rating'              => (float) $this->rating,
                'is_active'           => (bool) $this->is_active,
                'categories'          => $categories,
                'primary_category'    => $categories->first() ?? 'No Category',

                // الـ Badge الأول: VERIFIED | UNVERIFIED
                'verification_badge'  => $this->is_verified ? 'VERIFIED' : 'UNVERIFIED',

                // الـ Badge الثاني: PENDING | APPROVED | REJECTED
                // يأتي من moderation_status على جدول providers
                'approval_badge'      => strtoupper($this->moderation_status ?? 'PENDING'),

                // JOIN DATE — تاريخ إنشاء حساب المستخدم
                'join_date'           => $user?->created_at?->format('Y-m-d'),

                // LOCATION > CITY — من district تبع company_details
                'city'                => $district?->name_ar ?? $district?->name_en ?? null,
                'district_id'         => $isCompany ? $details?->district_id : null,
                'address_details'     => $isCompany ? $details?->address_details : null,
            ],

            // ═══════════════════════════════════════════════════════════════
            // Section 2: Identity & Correspondence (جدول بيانات التواصل)
            // Columns: First Name | Last Name | Representative Role |
            //          Language | Primary Contact (Email) | Phone Number
            // ═══════════════════════════════════════════════════════════════
            'identity' => [
                'first_name'          => $user?->first_name,
                'last_name'           => $user?->last_name,
                'full_name'           => $user
                    ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
                    : null,

                // REPRESENTATIVE ROLE — يعرض نوع المزود (company | freelancer)

                'language'            => $user?->settings_language ?? 'ar',
                'theme'               => $user?->settings_theme ?? 'light',

                // PRIMARY CONTACT (EMAIL)
                'email'               => $user?->email,

                // PHONE NUMBER
                'phone'               => $user?->phone,
            ],

            // ═══════════════════════════════════════════════════════════════
            // Section 3: Business Information (معلومات الأعمال)
            // يتغير محتواه بناءً على نوع المزود
            // ═══════════════════════════════════════════════════════════════
            'business' => [
                'brand_name'          => $this->brand_name,

                // حقول خاصة بالشركة فقط
                'tax_number'          => $isCompany ? $details?->tax_number        : null,
                'registration_no'     => $isCompany ? $details?->registration_no   : null,

                // حقول خاصة بالفريلانسر فقط
                'national_id'         => !$isCompany ? $details?->national_id       : null,
                'experience_years'    => !$isCompany ? $details?->experience_years  : null,
            ],

            // ═══════════════════════════════════════════════════════════════
            // Section 4: Security & Access (الأمان والوصول)
            // Badges: Email Verified | Phone Verified | Account Status |
            //         Provider Verified | Moderation Status
            // ═══════════════════════════════════════════════════════════════
            'security' => [
                // EMAIL VERIFIED
                'email_verified'      => $user?->email_verified_at
                    ? 'Verified'
                    : 'Not Verified',
                'is_email_verified'   => (bool) $user?->email_verified_at,

                // PHONE VERIFIED
                'phone_verified'      => $user?->phone_verified_at
                    ? 'Verified'
                    : 'Not Verified',
                'is_phone_verified'   => (bool) $user?->phone_verified_at,

                // ACCOUNT STATUS — من جدول users (active | inactive | banned)
                'account_status'      => $user?->status ?? 'active',

                // PROVIDER VERIFIED — من جدول providers
                'provider_verified'   => $this->is_verified ? 'Verified' : 'Pending',
                'is_verified'         => (bool) $this->is_verified,

                // MODERATION STATUS — من جدول providers
                'moderation_status'   => $this->moderation_status,

                // إضافي للـ Frontend
                'is_profile_completed' => (bool) ($user?->is_profile_completed ?? false),
            ],
        ];
    }
}
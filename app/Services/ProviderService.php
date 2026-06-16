<?php

namespace App\Services;

use App\Models\Provider;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProviderService
{
    /**
     * إكمال ملف تعريف مزود الخدمة (شركة أو فريلانسر)
     */
    public function completeProfile(User $user, array $data): Provider
    {
        return DB::transaction(function () use ($user, $data) {
            
            // 1. إنشاء سجل المزود الأساسي (الـ ULID سيتم توليده تلقائياً عبر الموديل إن كان مفعلاً)
            $provider = Provider::create([
                'user_id'           => $user->id,
                'brand_name'        => $data['brand_name'],
                'provider_type'     => $data['provider_type'],
                'is_verified'       => false,
                'moderation_status' => 'pending',
            ]);


            $provider->categories()->sync($data['categories']);
            if ($data['provider_type'] === 'company') {
                $this->createCompanyDetails($provider, $data);
            } elseif ($data['provider_type'] === 'freelancer') {
                $this->createFreelancerDetails($provider, $data);
            } else {
                throw new InvalidArgumentException('نوع مزود الخدمة غير مدعوم بالنظام.');
            }

            // 3. تحديث حالة المستخدم ليتم قفل الرابط عبر الميدل-وير فوراً
            $user->update([
                'is_profile_completed' => true
            ]);

            return $provider;
        });
    }
   
public function findProviderById(string $id)
    {
        $provider = Provider::with('user')->find($id);

        if (!$provider) {
            throw new ModelNotFoundException('المزود غير موجود في النظام.');
        }

        return $provider;
    }
    /**
     * حفظ بيانات الشركة
     */
    protected function createCompanyDetails(Provider $provider, array $data): void
    {
        $provider->companyDetails()->forceCreate([
            'tax_number'      => $data['tax_number'] ?? throw new InvalidArgumentException('الرقم الضريبي مطلوب للشركات.'),
            'district_id'       => $data['district_id'],
            'address_details'   => $data['address_details'] ?? null,
            'registration_no' => $data['registration_no'] ?? throw new InvalidArgumentException('رقم السجل التجاري مطلوب للشركات.'),
        ]);
    }

    /**
     * حفظ بيانات الفريلانسر
     */
    protected function createFreelancerDetails(Provider $provider, array $data): void
    {
        $provider->freelancerDetails()->create([
            'national_id' => $data['national_id'] ?? throw new InvalidArgumentException('الرقم الوطني مطلوب للأفراد.'),
            'experience_years' => $data['experience_years'] ?? 0, 
        ]);
    }
}
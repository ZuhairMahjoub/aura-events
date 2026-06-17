<?php

namespace Database\Factories;

use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ListingFactory extends Factory
{
    protected $model = Listing::class;

    public function definition(): array
    {
        return [
            // توليد معرف فريد من نوع ULID متناسق مع بقية الجداول
            'id' => (string) Str::ulid(),
            
            // يتم تمريره ديناميكياً من الـ Seeder لربطه بالمزود الصحيح
            'provider_id' => null, 

            // دعم الحقول المترجمة (بفرض أن الـ Casts في الموديل مجهزة كـ array أو json)
            'title' => [
                'en' => $this->faker->words(3, true) . ' Premium Service',
                'ar' => 'خدمة ' . $this->faker->word . ' المميزة والاحترافية',
            ],
            'description' => [
                'en' => $this->faker->paragraph(2),
                'ar' => 'وصف تفصيلي شامل ومميز مخصص لتغطية كافة متطلبات تنظيم الحفل أو الفعالية الخاصة بك.',
            ],

            // النوع الافتراضي (سيقوم الـ Seeder بتعديله صراحة في حلقة الـ foreach)
            'listing_type' => $this->faker->randomElement(['package', 'service', 'physical_product']),
            
            // أرقام عشوائية لمعرفات التصنيفات والمناطق (تأكد من وجود Seeders لها أو استبدلها بـ العلاقات)
            'category_id' => $this->faker->numberBetween(1, 5), 
            'district_id' => $this->faker->numberBetween(1, 10),
            
            'material_composition' => null, // مخصص للمنتجات المادية فقط إذا لزم الأمر
            'secondary_contact_number' => '09' . $this->faker->numerify('########'),

            // سياسات الإلغاء التي تفحصها الـ BookingService
            'cancel_before_acceptance' => $this->faker->boolean(80), // احتمال 80% أن يسمح بالإلغاء قبل القبول
            'cancel_after_acceptance' => $this->faker->boolean(30),  // احتمال 30% أن يسمح بالإلغاء بعد القبول
            'cancel_before_payment' => $this->faker->boolean(50),   // تنتهي نافذة الإلغاء فور الدفع

            // يتم ضبطها تلقائياً للقاعات داخل الـ Seeder
            'is_provider_location_based' => false, 
            // 'moderation_status' => 'pending_approval', // الإعلانات تظهر معتمدة فوراً للاختبار السلس
            // 'images' => [], // مصفوفة فارغة للصور في مرحلة الـ Seeding الحالية
        ];
    }
}
<?php

namespace Database\Factories;

use App\Models\ListingVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ListingVariantFactory extends Factory
{
    protected $model = ListingVariant::class;

    public function definition(): array
    {
        return [
            // توليد معرف فريد من نوع ULID للـ Variant
            'id' => (string) Str::ulid(),
            
            // يتم تمريره ديناميكياً من الـ Seeder لربطه بالإعلان الأب
            'listing_id' => null, 

            // أسماء باقات مترجمة تناسب القاعات أو الخدمات أو المنتجات
            'variant_name' => [
                'en' => $this->faker->randomElement(['Standard Package', 'Premium VIP Setup', 'Basic Option']),
                'ar' => $this->faker->randomElement(['الباقة القياسية المتكاملة', 'التجهيز الفاخر VIP', 'الخيار الأساسي السريع']),
            ],

            // أسعار منطقية بالعملة المحلية للاختبار (مثل الأسعار المتداولة في سوريا)
            'price' => $this->faker->randomElement([500000, 1500000, 3000000, 5000000]),
            'currency' => 'SYP',
            'price_type' => 'fixed',
            
            // يتم ضبطها في الـ Seeder فقط إذا كان نوع الإعلان 'physical_product'
            'stock_quantity' => null, 
            // 'attributes' => null, // لأي خصائص إضافية مخصصة مستقبلاً
        ];
    }
}
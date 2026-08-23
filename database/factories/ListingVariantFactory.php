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
            'id' => (string) Str::ulid(),
            'listing_id' => null,

            'variant_name' => [
                'en' => $this->faker->randomElement(['Standard Package', 'Premium VIP Setup', 'Basic Option']),
                'ar' => $this->faker->randomElement(['الباقة القياسية المتكاملة', 'التجهيز الفاخر VIP', 'الخيار الأساسي السريع']),
            ],

            'price' => $this->faker->randomElement([500000, 1500000, 3000000, 5000000]),
            'currency' => 'SYP',
            'price_type' => 'fixed',
            'stock_quantity' => null,

            'dynamic_attributes' => [
                'capacity' => $this->faker->numberBetween(20, 250),
            ],
        ];
    }
}
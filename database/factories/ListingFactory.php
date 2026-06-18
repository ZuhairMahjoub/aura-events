<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\District;
use App\Models\Listing;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ListingFactory extends Factory
{
    protected $model = Listing::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement([
            'package',
            'service',
            'physical_product',
        ]);

        return [
            'id' => (string) Str::ulid(),

            'provider_id' => Provider::factory()->approved(),

            'category_id' => Category::query()->inRandomOrder()->value('id') ?? 1,
            'district_id' => District::query()->inRandomOrder()->value('id') ?? 1,

            'title' => [
                'en' => $this->faker->words(3, true) . ' Premium Service',
                'ar' => 'خدمة ' . $this->faker->word . ' المميزة والاحترافية',
            ],

            'description' => [
                'en' => $this->faker->paragraph(2),
                'ar' => 'وصف تفصيلي شامل ومميز مخصص لتغطية كافة متطلبات تنظيم الحفل أو الفعالية الخاصة بك.',
            ],

            'listing_type' => $type,
            'material_composition' => $type === 'physical_product'
                ? $this->faker->words(3, true)
                : null,

            'secondary_contact_number' => '09' . $this->faker->numerify('########'),

            'cancel_before_acceptance' => $this->faker->boolean(80),
            'cancel_after_acceptance' => $this->faker->boolean(30),
            'cancel_before_payment' => $this->faker->boolean(50),
            'is_provider_location_based' => $type !== 'service',

            'moderation_status' => 'approved',
            'rejection_reason' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'moderation_status' => 'approved',
        ]);
    }

    public function physicalProduct(): static
    {
        return $this->state(fn () => [
            'listing_type' => 'physical_product',
            'material_composition' => $this->faker->words(3, true),
        ]);
    }

    public function service(): static
    {
        return $this->state(fn () => [
            'listing_type' => 'service',
            'material_composition' => null,
            'is_provider_location_based' => false,
        ]);
    }

    public function package(): static
    {
        return $this->state(fn () => [
            'listing_type' => 'package',
            'material_composition' => null,
        ]);
    }
}
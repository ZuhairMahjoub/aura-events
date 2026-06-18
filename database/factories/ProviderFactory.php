<?php
namespace Database\Factories;

use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProviderFactory extends Factory
{
    protected $model = Provider::class;
public function definition(): array
{
    return [
        'id'            => (string) Str::ulid(),
        'brand_name'    => $this->faker->company,
        'provider_type' => $this->faker->randomElement(['company', 'freelancer']),
        'rating'        => 0.00,
        'is_verified'   => false,
        'is_active'     => true,
        'moderation_status' => 'pending',   // ✅ أضف هذا
    ];
}

public function approved(): static
{
    return $this->state(fn (array $attributes) => [
        'is_verified'       => true,
        'moderation_status' => 'approved',  // ✅ أضف هذا
        'rating'            => $this->faker->randomFloat(2, 3, 5),
    ]);
}
public function company(): static
{
    return $this->state(fn (array $attributes) => [
        'provider_type' => 'company',
    ]);
}

public function freelancer(): static
{
    return $this->state(fn (array $attributes) => [
        'provider_type' => 'freelancer',
    ]);
}
 
}
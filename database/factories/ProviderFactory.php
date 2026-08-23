<?php
namespace Database\Factories;

use App\Models\Provider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProviderFactory extends Factory
{
    protected $model = Provider::class;

    public function definition(): array
    {
        return [
            'id'                => (string) Str::ulid(),
            'user_id'           => User::factory(),   // ⭐ الإضافة المطلوبة
            'brand_name'        => $this->faker->company,
            'provider_type'     => $this->faker->randomElement(['company', 'freelancer']),
            'rating'            => 0.00,
            'is_verified'       => false,
            'is_active'         => true,
            'moderation_status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified'       => true,
            'moderation_status' => 'approved',
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
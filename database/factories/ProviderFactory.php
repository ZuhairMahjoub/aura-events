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
            'id' => (string) Str::ulid(),
            // ملاحظة: الـ user_id سيتم تمريره من الـ Seeder عند الإنشاء
            'brand_name' => $this->faker->company,
            'provider_type' => $this->faker->randomElement(['company', 'freelancer']),
            'rating' => 0.00,
            'is_verified' => false, // الافتراضي غير معتمد
            'is_active' => true,
        ];
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
    // state لإنشاء مزود خدمة معتمد (Approved)
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => true,
            'rating' => $this->faker->randomFloat(2, 3, 5), // إعطاء تقييم عشوائي بين 3 و 5 للمعتمدين
        ]);
    }
}
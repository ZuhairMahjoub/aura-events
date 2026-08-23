<?php

namespace Database\Factories;

use App\Models\CompanyDetail;
use App\Models\District;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CompanyDetailFactory extends Factory
{
    protected $model = CompanyDetail::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'provider_id' => Provider::factory()->company(),
            'district_id' => District::query()->inRandomOrder()->value('id') ?? 1,
            'address_details' => $this->faker->streetAddress(),
            'tax_number' => $this->faker->unique()->numerify('TAX########'),
            'registration_no' => $this->faker->unique()->numerify('REG########'),
        ];
    }
}
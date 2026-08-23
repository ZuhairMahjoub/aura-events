<?php

namespace Database\Factories;

use App\Models\FreelancerDetail;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FreelancerDetailFactory extends Factory
{
    protected $model = FreelancerDetail::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'provider_id' => Provider::factory()->freelancer(),
            'national_id' => $this->faker->unique()->numerify('###########'),
            'experience_years' => $this->faker->numberBetween(0, 15),
        ];
    }
}
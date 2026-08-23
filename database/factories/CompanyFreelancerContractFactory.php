<?php

namespace Database\Factories;

use App\Models\CompanyFreelancerContract;
use App\Models\JobOffer;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CompanyFreelancerContractFactory extends Factory
{
    protected $model = CompanyFreelancerContract::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'company_id' => Provider::factory()->company()->approved(),
            'freelancer_id' => Provider::factory()->freelancer()->approved(),
            'job_offer_id' => JobOffer::factory(),
            'status' => $this->faker->randomElement([
                'pending',
                'active',
                'rejected',
                'expired',
            ]),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
        ]);
    }
}
<?php

namespace Database\Factories;

use App\Models\JobOffer;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class JobOfferFactory extends Factory
{
    protected $model = JobOffer::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),

            'company_id' => Provider::factory()->company()->approved(),

            'job_title' => $this->faker->randomElement([
                'Event Photographer',
                'Sound Technician',
                'Lighting Operator',
                'Wedding Coordinator',
                'Stage Decor Assistant',
            ]),

            'time_condition' => $this->faker->randomElement([
                'Permanent',
                'Temporary',
                'Contract',
            ]),

            'event_type' => $this->faker->randomElement([
                'Wedding',
                'Conference',
                'Concert',
                'Private Party',
            ]),

            'job_start_date' => now()->addDays($this->faker->numberBetween(7, 45))->toDateString(),
            'application_deadline' => now()->addDays($this->faker->numberBetween(1, 20))->toDateString(),

            'salary' => $this->faker->randomElement([
                250000,
                500000,
                750000,
                1000000,
                1500000,
            ]),

            'payment_system' => $this->faker->randomElement([
                'Per Event',
                'Monthly',
                'Hourly',
            ]),

            'specific_event_association' => $this->faker->optional()->randomElement([
                'Summer wedding season',
                'Corporate conference',
                'VIP private event',
            ]),

            'experience_level' => $this->faker->randomElement([
                'Junior',
                'Mid',
                'Senior',
            ]),

            'company_equipment_provided' => $this->faker->boolean(70),

            'job_requirements_and_scope' => $this->faker->paragraph(),

            'contact_info' => $this->faker->safeEmail(),
        ];
    }
}
<?php

namespace Database\Factories;

use App\Models\FreelancerBlockedDate;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FreelancerBlockedDateFactory extends Factory
{
    protected $model = FreelancerBlockedDate::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'freelancer_id' => Provider::factory()->freelancer()->approved(),
            'blocked_date' => now()->addDays($this->faker->numberBetween(1, 60))->toDateString(),
            'start_time' => null,
            'end_time' => null,
            'source' => 'manual',
            'booking_id' => null,
        ];
    }

    public function manual(): static
    {
        return $this->state(fn () => ['source' => 'manual', 'booking_id' => null]);
    }

    public function partial(string $startTime, string $endTime): static
    {
        return $this->state(fn () => ['start_time' => $startTime, 'end_time' => $endTime]);
    }

    public function fromBooking(string $bookingId): static
    {
        return $this->state(fn () => ['source' => 'booking', 'booking_id' => $bookingId]);
    }
}
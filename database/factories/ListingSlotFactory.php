<?php

namespace Database\Factories;

use App\Models\ListingSlot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ListingSlotFactory extends Factory
{
    protected $model = ListingSlot::class;

    public function definition(): array
    {
        $startHour = fake()->numberBetween(8, 18);

        $startTime = sprintf('%02d:00:00', $startHour);

        $endTime = sprintf(
            '%02d:00:00',
            min($startHour + fake()->numberBetween(2, 6), 23)
        );

        return [
            'id' => (string) Str::ulid(),

            'listing_availability_id' => null,

            'slot_name' => fake()->words(2, true),

            'start_time' => $startTime,

            'end_time' => $endTime,

            'remaining_capacity' => fake()->numberBetween(1, 10),
        ];
    }
}
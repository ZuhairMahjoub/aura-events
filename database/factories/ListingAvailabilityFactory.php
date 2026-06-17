<?php

namespace Database\Factories;

use App\Models\ListingAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ListingAvailabilityFactory extends Factory
{
    protected $model = ListingAvailability::class;

    public function definition(): array
    {
        static $usedDates = [];

        do {
            $date = fake()
                ->dateTimeBetween('+1 day', '+3 months')
                ->format('Y-m-d');
        } while (in_array($date, $usedDates));

        $usedDates[] = $date;

        return [
            'id' => (string) Str::ulid(),

            // يتم تمريرها من Seeder
            'listing_variant_id' => null,

            'available_date' => $date,
        ];
    }
}
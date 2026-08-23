<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Listing;
use App\Models\ListingSlot;
use App\Models\ListingVariant;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'provider_id' => Provider::factory(),
            'listing_id' => Listing::factory(),
            'listing_variant_id' => ListingVariant::factory(),
            'listing_slot_id' => null,
            'booking_type' => $this->faker->randomElement([
                'physical_product',
                'service',
                'package',
            ]),
            'status' => $this->faker->randomElement([
                'pending',
                'accepted',
                'confirmed',
                'completed',
            ]),
            'payment_status' => $this->faker->randomElement([
                'unpaid',
                'paid',
            ]),
            'quantity' => $this->faker->numberBetween(1, 4),
            'total_price' => $this->faker->randomFloat(2, 50000, 900000),
            'currency' => 'SYP',
            'booked_date' => $this->faker->optional()->dateTimeBetween('now', '+2 months')?->format('Y-m-d'),
            'booked_start_time' => null,
            'booked_end_time' => null,
            'metadata' => [
                'source' => 'factory',
            ],
            'customer_notes' => $this->faker->optional()->sentence(),
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'cancelled_by' => null,
            'payment_reference' => $this->faker->optional()->bothify('PAY-####-????'),
        ];
    }
}
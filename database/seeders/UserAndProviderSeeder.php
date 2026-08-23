<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Category;
use App\Models\CompanyDetail;
use App\Models\CompanyFreelancerContract;
use App\Models\District;
use App\Models\FreelancerDetail;
use App\Models\JobOffer;
use App\Models\Listing;
use App\Models\ListingAvailability;
use App\Models\ListingSlot;
use App\Models\ListingVariant;
use App\Models\Provider;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class UserAndProviderSeeder extends Seeder
{
    public function run(): void
    {
        $clientRole = Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'api']);
        $providerRole = Role::firstOrCreate(['name' => 'provider', 'guard_name' => 'api']);

        $categories = Category::query()->get();
        $districts = District::query()->get();

        if ($categories->isEmpty() || $districts->isEmpty()) {
            $this->command?->warn('Run CategorySeeder and GovernorateAndDistrictSeeder first.');
            return;
        }

        $clients = User::factory()
            ->count(10)
            ->verified()
            ->create()
            ->each(fn (User $user) => method_exists($user, 'assignRole') ? $user->assignRole($clientRole) : null);

        $companies = $this->createProviders('company', 4, $providerRole, $categories, $districts);
        $freelancers = $this->createProviders('freelancer', 8, $providerRole, $categories, $districts);

        $productVariants = collect();
        $serviceVariants = collect();
        $packageVariants = collect();

        foreach ($companies as $company) {
            $productVariants = $productVariants->merge(
                $this->createListings($company, $categories, $districts, 'physical_product', 2)
            );

            $serviceVariants = $serviceVariants->merge(
                $this->createListings($company, $categories, $districts, 'service', 2)
            );

            $offer = JobOffer::factory()->create([
                'company_id' => $company->id,
            ]);

            foreach ($freelancers->random(min(3, $freelancers->count())) as $freelancer) {
                CompanyFreelancerContract::factory()
                    ->active()
                    ->create([
                        'company_id' => $company->id,
                        'freelancer_id' => $freelancer->id,
                        'job_offer_id' => $offer->id,
                    ]);
            }

            $packageVariants->push(
                $this->createPackageListing($company, $categories, $districts, $productVariants, $freelancers)
            );
        }

        $this->createBookings($clients, $productVariants, $serviceVariants, $packageVariants->filter());
    }

    private function createProviders(
        string $type,
        int $count,
        Role $role,
        Collection $categories,
        Collection $districts
    ): Collection {
        return User::factory()
            ->count($count)
            ->verified()
            ->create()
            ->map(function (User $user) use ($type, $role, $categories, $districts) {
                if (method_exists($user, 'assignRole')) {
                    $user->assignRole($role);
                }

                $provider = Provider::factory()
                    ->approved()
                    ->state(['provider_type' => $type])
                    ->create([
                        'user_id' => $user->id,
                    ]);

                if (method_exists($provider, 'categories')) {
                    $provider->categories()->sync(
                        $categories->random(min(2, $categories->count()))->pluck('id')
                    );
                }

                if ($type === 'company') {
                    CompanyDetail::factory()->create([
                        'provider_id' => $provider->id,
                        'district_id' => $districts->random()->id,
                    ]);
                } else {
                    FreelancerDetail::factory()->create([
                        'provider_id' => $provider->id,
                    ]);
                }

                return $provider;
            });
    }

    private function createListings(
        Provider $provider,
        Collection $categories,
        Collection $districts,
        string $type,
        int $count
    ): Collection {
        return collect(range(1, $count))->map(function () use ($provider, $categories, $districts, $type) {
            $listing = Listing::factory()->create([
                'provider_id' => $provider->id,
                'category_id' => $categories->random()->id,
                'district_id' => $districts->random()->id,
                'listing_type' => $type,
                'material_composition' => $type === 'physical_product' ? fake()->words(3, true) : null,
                'is_provider_location_based' => $type !== 'service',
                'moderation_status' => 'approved',
            ]);

            $variant = ListingVariant::factory()->create([
                'listing_id' => $listing->id,
                'price_type' => $type === 'service' ? 'hourly' : 'fixed',
                'stock_quantity' => $type === 'physical_product' ? fake()->numberBetween(10, 50) : null,
                'dynamic_attributes' => [
                    'type' => $type,
                    'capacity' => fake()->numberBetween(20, 250),
                ],
            ]);

            $this->createAvailabilityAndSlots($variant);

            return $variant->load('listing');
        });
    }

    private function createAvailabilityAndSlots(ListingVariant $variant): void
    {
        foreach (range(1, 4) as $index) {
            $availability = ListingAvailability::factory()->create([
                'listing_variant_id' => $variant->id,
                'available_date' => now()->addDays($index * 3)->toDateString(),
            ]);

            foreach ([[9, 12], [14, 17], [18, 21]] as [$start, $end]) {
                ListingSlot::factory()->create([
                    'listing_availability_id' => $availability->id,
                    'slot_name' => [
                        'ar' => 'موعد متاح',
                        'en' => 'Available Slot',
                    ],
                    'start_time' => sprintf('%02d:00:00', $start),
                    'end_time' => sprintf('%02d:00:00', $end),
                    'remaining_capacity' => fake()->numberBetween(1, 10),
                ]);
            }
        }
    }

    private function createPackageListing(
        Provider $company,
        Collection $categories,
        Collection $districts,
        Collection $productVariants,
        Collection $freelancers
    ): ?ListingVariant {
        $listing = Listing::factory()->create([
            'provider_id' => $company->id,
            'category_id' => $categories->random()->id,
            'district_id' => $districts->random()->id,
            'listing_type' => 'package',
            'moderation_status' => 'approved',
        ]);

        $variant = ListingVariant::factory()->create([
            'listing_id' => $listing->id,
            'price' => fake()->randomElement([2500000, 4000000, 6500000]),
            'price_type' => 'fixed',
            'stock_quantity' => 10,
            'dynamic_attributes' => [
                'capacity' => fake()->numberBetween(80, 300),
                'includes_team' => true,
            ],
        ]);

        return $variant->load('listing');
    }

    private function createBookings(
        Collection $clients,
        Collection $productVariants,
        Collection $serviceVariants,
        Collection $packageVariants
    ): void {
        if (!class_exists(Booking::class)) {
            return;
        }

        foreach ($productVariants->take(6) as $variant) {
            $this->createBooking($clients->random(), $variant);
        }

        foreach ($serviceVariants->take(6) as $variant) {
            $slot = $variant->availabilities()
                ->with('slots')
                ->get()
                ->pluck('slots')
                ->flatten()
                ->first();

            $this->createBooking($clients->random(), $variant, $slot);
        }

        foreach ($packageVariants->take(4) as $variant) {
            $this->createBooking($clients->random(), $variant);
        }
    }

    private function createBooking(User $client, ListingVariant $variant, ?ListingSlot $slot = null): void
    {
        $listing = $variant->listing;
        $quantity = $listing->listing_type === 'service' ? 1 : fake()->numberBetween(1, 3);

        Booking::factory()->create([
            'user_id' => $client->id,
            'provider_id' => $listing->provider_id,
            'listing_id' => $listing->id,
            'listing_variant_id' => $variant->id,
            'listing_slot_id' => $slot?->id,
            'booking_type' => $listing->listing_type,
            'status' => fake()->randomElement(['pending', 'accepted', 'confirmed', 'completed']),
            'payment_status' => fake()->randomElement(['unpaid', 'paid']),
            'quantity' => $quantity,
            'total_price' => (float) $variant->price * $quantity,
            'currency' => 'SYP',
            'booked_date' => $slot?->availability?->available_date?->toDateString(),
            'booked_start_time' => $slot?->start_time,
            'booked_end_time' => $slot?->end_time,
            'metadata' => [
                'source' => 'demo_seeder',
                'event_type' => fake()->randomElement(['wedding', 'conference', 'party']),
                'guest_count' => fake()->numberBetween(30, 250),
            ],
            'customer_notes' => fake()->optional()->sentence(),
        ]);
    }
}
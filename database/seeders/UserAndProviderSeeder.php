<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Provider;
use Illuminate\Database\Seeder;
use App\Models\Role;

class UserAndProviderSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure both roles exist in the database
        $organizerRole = Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'api']);
        $providerRole = Role::firstOrCreate(['name' => 'provider', 'guard_name' => 'api']);
        
        // ---------------------------------------------------------
        // GROUP 1: Create 20 Organizers
        // ---------------------------------------------------------
        User::factory()->count(20)->verified()->create()->each(function ($user) use ($organizerRole) {
            
            // Assign ONLY the organizer role
            $user->assignRole($organizerRole);
            
        });

        // ---------------------------------------------------------
        // GROUP 2: Create 20 Providers (with Provider profiles)
        // ---------------------------------------------------------
        User::factory()->count(20)->verified()->create()->each(function ($user) use ($providerRole) {
            
            // Assign ONLY the provider role
            $user->assignRole($providerRole);
            
            // Create the corresponding provider profile using the user's ULID
            Provider::factory()->approved()->create([
                'user_id' => $user->id
            ]);
            
        });
    }
}
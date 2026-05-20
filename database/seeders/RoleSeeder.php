<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;  

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $webRoles = ['admin', 'provider', 'organizer', 'client'];
        foreach ($webRoles as $webrole) {
            Role::firstOrCreate(['name' => $webrole, 'guard_name' => 'web']);
        }
    
        $apiRoles = ['organizer', 'provider', 'client'];
        foreach ($apiRoles as $apiRole) {
            Role::firstOrCreate(['name' => $apiRole, 'guard_name' => 'api']);
        }
    }
}
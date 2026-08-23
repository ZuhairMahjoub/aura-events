<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role; // تأكد من استدعاء الموديل المخصص عندك
use App\Models\Permission; // تأكد من استدعاء الموديل المخصص عندك
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $apiRoles = ['admin', 'provider', 'organizer', 'client'];
        foreach ($apiRoles as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'api'],
                ['id' => (string) Str::ulid()]
            );
        }

        $permissions = ['view listings', 'create listings', 'update listings', 'delete listings'];
        foreach ($permissions as $permName) {
            Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'api'],
                ['id' => (string) Str::ulid()]
            );
        }

        $provider = Role::where('name', 'provider')->where('guard_name', 'api')->first();
        $provider->syncPermissions(['view listings', 'create listings', 'update listings', 'delete listings']);

        $organizer = Role::where('name', 'organizer')->where('guard_name', 'api')->first();
        $organizer->syncPermissions(['view listings']);

        $admin = Role::where('name', 'admin')->where('guard_name', 'api')->first();
        $admin->syncPermissions(Permission::where('guard_name', 'api')->get());

        $adminUser = User::firstOrCreate(
            ['email' => 'admin@aura.com'],
            [
                'first_name' => 'Zuhair',
                'last_name' => 'Admin',
                'phone' => '0912345678',
                'password' => Hash::make('Admin@12345'),
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );

        $adminUser->assignRole($admin);

        $this->command->info('Seeding completed successfully!');
    }
}
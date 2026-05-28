<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;  
use App\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // 1. تنظيف الكاش الخاص بسباتي لتجنب أي تضارب أثناء الـ Seeding
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. تعريف الأدوار الموحدة على حارس الـ API فقط
        $apiRoles = ['admin', 'provider', 'organizer', 'client'];
        
        foreach ($apiRoles as $roleName) {
            Role::firstOrCreate([
                'name'       => $roleName, 
                'guard_name' => 'api' // 🔒 توحيد الحارس بالكامل ليتوافق مع الـ Tokens
            ]);
        }

        // 3. تعريف صلاحيات كتلة العروض (Listings Permissions)
        $listingPermissions = [
            'view listings',
            'create listings',
            'update listings',
            'delete listings'
        ];

        foreach ($listingPermissions as $permissionName) {
            Permission::firstOrCreate([
                'name'       => $permissionName, 
                'guard_name' => 'api'
            ]);
        }

        // 4. ربط الصلاحيات بالأدوار (Role-Permission Mapping)

        // أ. منح دور المزود (Provider) الصلاحيات الكاملة لإدارة عروضه
        $providerRole = Role::where(['name' => 'provider', 'guard_name' => 'api'])->first();
        if ($providerRole) {
            $providerRole->syncPermissions($listingPermissions);
        }

        // ب. منح دور المنظم (Organizer) صلاحية استعراض العروض فقط ليختار منها للمناسبات
        $organizerRole = Role::where(['name' => 'organizer', 'guard_name' => 'api'])->first();
        if ($organizerRole) {
            $organizerRole->syncPermissions(['view listings']);
        }

        // ج. منح دور الـ Admin الصلاحية المطلقة على كل شيء بالسيستم
        $adminRole = Role::where(['name' => 'admin', 'guard_name' => 'api'])->first();
        if ($adminRole) {
            // الـ Admin يأخذ كل الصلاحيات الموجودة في جدول الـ Permissions تلقائياً
            $adminRole->syncPermissions(Permission::where('guard_name', 'api')->get());
        }
    }
}
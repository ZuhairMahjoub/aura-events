<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Provider;
use App\Models\Role;

class UserAndProviderSeeder extends Seeder
{
    public function run(): void
    {
        // 1. التأكد من وجود الرتب (Roles) الأساسية في النظام قبل البدء
        $organizerRole = Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'api']);
        $providerRole  = Role::firstOrCreate(['name' => 'provider', 'guard_name' => 'api']);
        
        // ---------------------------------------------------------
        // المجموعة الأولى: إنشاء 20 منظم حفلات (Organizers / Customers)
        // ---------------------------------------------------------
        User::factory()->count(20)->verified()->create()->each(function ($user) use ($organizerRole) {
            
            // تعيين رتبة منظم فقط
            $user->assignRole($organizerRole);
            
        });

        // ---------------------------------------------------------
        // المجموعة الثانية: إنشاء 20 مزود خدمة (الحساب والملف الشخصي فقط)
        // ---------------------------------------------------------
        User::factory()->count(20)->verified()->create()->each(function ($user) use ($providerRole) {
            
            // أ- تعيين رتبة المزود للمستخدم الحالي
            $user->assignRole($providerRole);
            
            // ب- إنشاء ملف المزود التجاري (Profile) وربطه بـ حساب المستخدم عبر الـ ULID/ID
            Provider::factory()->approved()->create([
                'user_id' => $user->id
            ]);

            // [تم تنظيف وحذف جزء إنشاء الـ Listings وكافة الجداول التابعة لها هنا ليكون الحساب جديداً تماماً]
            
        });
    }
}
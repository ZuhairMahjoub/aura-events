<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GovernorateAndDistrictSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. إدخال محافظة دمشق وتثبيتها كـ أول سجل (ID: 1)
        $governorateId = DB::table('governorates')->insertGetId([
            'id'         => 1,
            'name_ar'    => 'دمشق',
            'name_en'    => 'Damascus',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. مصفوفة تضم كافة مناطق وأحياء دمشق الكبرى المقسمة هندسياً للفلترة
        $districts = [
            ['name_ar' => 'المزة', 'name_en' => 'Mezzeh'],
            ['name_ar' => 'مشروع دمر', 'name_en' => 'Dummar Project'],
            ['name_ar' => 'كفرسوسة', 'name_en' => 'Kfar Souseh'],
            ['name_ar' => 'الشعلان', 'name_en' => 'Shaalan'],
            ['name_ar' => 'القصاع', 'name_en' => 'Al-Qassaa'],
            ['name_ar' => 'باب توما', 'name_en' => 'Bab Touma'],
            ['name_ar' => 'التجارة', 'name_en' => 'Al-Tijarah'],
            ['name_ar' => 'الميدان', 'name_en' => 'Al-Midan'],
            ['name_ar' => 'البرامكة', 'name_en' => 'Al-Baramkeh'],
            ['name_ar' => 'المالكي', 'name_en' => 'Al-Malki'],
            ['name_ar' => 'الروضة', 'name_en' => 'Al-Rawda'],
            ['name_ar' => 'أبو رمانة', 'name_en' => 'Abu Rummaneh'],
            ['name_ar' => 'المهاجرين', 'name_en' => 'Al-Muhajirin'],
            ['name_ar' => 'الركن الدين', 'name_en' => 'Rukn Al-Din'],
            ['name_ar' => 'الصالحية', 'name_en' => 'Al-Salihiyah'],
            ['name_ar' => 'المزرعة', 'name_en' => 'Al-Mazraa'],
            ['name_ar' => 'العدوي', 'name_en' => 'Al-Adawi'],
            ['name_ar' => 'القصور', 'name_en' => 'Al-Qusour'],
            ['name_ar' => 'برزة', 'name_en' => 'Barzeh'],
            ['name_ar' => 'القابون', 'name_en' => 'Al-Qaboun'],
            ['name_ar' => 'القدم', 'name_en' => 'Al-Qadam'],
            ['name_ar' => 'الزاهرة', 'name_en' => 'Al-Zahira'],
            ['name_ar' => 'دمر البلد', 'name_en' => 'Dummar البلد'],
            ['name_ar' => 'قدسيا (الضاحية)', 'name_en' => 'Dahiyat Qudsaya'],
            ['name_ar' => 'كفرسوسة اللوان', 'name_en' => 'Kfar Souseh Al-Lawan'],
            ['name_ar' => 'المزة فيلات شرقية', 'name_en' => 'Mezzeh Eastern Villas'],
            ['name_ar' => 'المزة فيلات غربية', 'name_en' => 'Mezzeh Western Villas'],
            ['name_ar' => 'المزة 86', 'name_en' => 'Mezzeh 86'],
        ];

        // تجهيز البيانات للربط مع دمشق (governorate_id = 1)
        $dataToInsert = [];
        foreach ($districts as $district) {
            $dataToInsert[] = [
                'governorate_id' => $governorateId,
                'name_ar'        => $district['name_ar'],
                'name_en'        => $district['name_en'],
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        // إدخال كل البيانات بطلب واحد سريع (Bulk Insert)
        DB::table('districts')->insert($dataToInsert);
    }
}
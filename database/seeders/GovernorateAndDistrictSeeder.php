<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GovernorateAndDistrictSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::table('districts')->truncate();
        DB::table('governorates')->truncate();

        $data = [
            'دمشق' => ['Damascus' => [
                'المزة' => 'Mazzeh', 
                'الميدان' => 'Midan', 
                'باب توما' => 'Bab Touma', 
                'المالكي' => 'Al-Maliki', 
                'أبو رمانة' => 'Abu Rummaneh', 
                'المهاجرين' => 'Al-Muhajireen', 
                'العدوي' => 'Al-Adawi', 
                'القصاع' => 'Al-Qasaa'
            ]],
        ];

        foreach ($data as $arGov => $enGovData) {
            $enGov = array_key_first($enGovData);
            $districts = $enGovData[$enGov];

            $govId = DB::table('governorates')->insertGetId([
                'name' => json_encode(['ar' => $arGov, 'en' => $enGov], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($districts as $arName => $enName) {
                DB::table('districts')->insert([
                    'governorate_id' => $govId,
                    'name' => json_encode(['ar' => $arName, 'en' => $enName], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::enableForeignKeyConstraints();
    }
}
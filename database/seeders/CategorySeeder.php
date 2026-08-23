<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('categories')->delete();

        $categories = [
            ['ar' => 'التصوير والتوثيق الرقمي',        'en' => 'Media & Photography'],
            ['ar' => 'الأنظمة الصوتية والمرئية',         'en' => 'Audio-Visual (AV) Systems'],
            ['ar' => 'الإضاءة والمؤثرات البصرية',        'en' => 'Lighting & Stage Effects'],
            ['ar' => 'تصميم المسارح والديكور',           'en' => 'Stage & Decor Design'],
            ['ar' => 'الضيافة والبوفيه المفتوح',          'en' => 'Catering & Hospitality'],
            ['ar' => 'تنظيم الفعاليات وإدارة الحشود',     'en' => 'Event Management & Crowd Control'],
            ['ar' => 'العروض الحية والترفيه',            'en' => 'Live Shows & Entertainment'],
            ['ar' => 'الخدمات اللوجستية والنقل',          'en' => 'Logistics & Transportation'],
        ];

        $now = now();

        $rows = array_map(function ($cat) use ($now) {
            return [
                'name'       => json_encode($cat, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $categories);

        DB::table('categories')->insert($rows);
    }
}
<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
    use Illuminate\Support\Facades\DB;
class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
  public function run(): void
{
    // استخدام delete بدلاً من truncate لتفادي مشاكل الـ Lock في بعض محركات قواعد البيانات
    
    DB::table('categories')->delete(); 

    $categories = [
        ['name_ar' => 'التصوير والتوثيق الرقمي', 'name_en' => 'Media & Photography'],
        ['name_ar' => 'الأنظمة الصوتية والمرئية', 'name_en' => 'Audio-Visual (AV) Systems'],
        ['name_ar' => 'الإضاءة والمؤثرات البصرية', 'name_en' => 'Lighting & Stage Effects'],
        ['name_ar' => 'تصميم المسارح والديكور', 'name_en' => 'Stage & Decor Design'],
        ['name_ar' => 'الضيافة والبوفيه المفتوح', 'name_en' => 'Catering & Hospitality'],
        ['name_ar' => 'تنظيم الفعاليات وإدارة الحشود', 'name_en' => 'Event Management & Crowd Control'],
        ['name_ar' => 'العروض الحية والترفيه', 'name_en' => 'Live Shows & Entertainment'],
        ['name_ar' => 'الخدمات اللوجستية والنقل', 'name_en' => 'Logistics & Transportation'],
    ];

    // إدخال جماعي مباشر فوري ⚡
    DB::table('categories')->insert($categories);
}
}

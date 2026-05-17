<?php

namespace Database\Seeders;

//use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $webRoles=['admin','provider','organizer'];
        foreach($webRoles as $webrole){
            Role::create([
                'name'=>$webrole,
                'guard_name'=>'api'
            ]);
        }
    
}
}

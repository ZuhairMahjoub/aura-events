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
        $webRoles=['admin','provider'];
        foreach($webRoles as $webrole){
            Role::firstOrCreate(['name'=>$webrole,'guard_name' => 'web']);
        }
    
            Role::firstOrCreate(['name'=>'organizer','guard_name' => 'api']);
}
}

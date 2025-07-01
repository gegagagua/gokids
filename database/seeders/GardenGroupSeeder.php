<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GardenGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\GardenGroup::create([
            'name' => 'პატარა ჯგუფი',
            'garden_id' => 4
        ]);

        \App\Models\GardenGroup::create([
            'name' => 'საშუალო ჯგუფი',
            'garden_id' => 5
        ]);
    }
}

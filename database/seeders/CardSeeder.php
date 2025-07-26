<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Card::create([
            'child_first_name' => 'თომა',
            'child_last_name' => 'ქველაძე',
            'father_name' => 'ლევან',
            'parent_name' => 'თამარ ჯავახიშვილი',
            'phone' => '555112233',
            'status' => 'active',
            'group_id' => 3,
            'parent_code' => 'PARENT1234',
        ]);
    }
}

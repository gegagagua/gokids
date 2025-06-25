<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Garden;

class GardenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */


    public function run(): void
    {
        Garden::insert([
            [
                'name' => 'ალიონას ბაღი',
                'address' => 'ვაჟა ფშაველა 22',
                'tax_id' => '123456789',
                'city_id' => 1,
                'phone' => '599123456',
                'email' => 'alionagarden@example.com',
                'password' => bcrypt('password123'),
            ],
            [
                'name' => 'მზიური',
                'address' => 'ჭავჭავაძის 45',
                'tax_id' => '987654321',
                'city_id' => 2,
                'phone' => '555987654',
                'email' => 'mziurigarden@example.com',
                'password' => bcrypt('securepass'),
            ],
            [
                'name' => 'ნუცუბიძის ბაღი',
                'address' => 'ნუცუბიძის 3',
                'tax_id' => '555443333',
                'city_id' => 3,
                'phone' => '577000111',
                'email' => 'nutsugarden@example.com',
                'password' => bcrypt('12345678'),
            ],
        ]);
    }
}

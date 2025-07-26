<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Country;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Country::insert([
            [
                'name' => 'საქართველო',
                'tariff' => 0.00, // Free
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'აზერბაიჯანი',
                'tariff' => 15.50,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'სომხეთი',
                'tariff' => 12.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'რუსეთი',
                'tariff' => 25.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'თურქეთი',
                'tariff' => 18.75,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'ირანი',
                'tariff' => 20.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'უკრაინა',
                'tariff' => 0.00, // Free
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'ბელარუსი',
                'tariff' => 22.50,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

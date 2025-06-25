<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\City;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            'თბილისი', 'ბათუმი', 'გორი', 'რუსთავი', 'ქუთაისი',
            'ზესტაფონი', 'თელავი', 'ფოთი', 'ქობულეთი', 'მცხეთა'
        ];

        foreach ($cities as $city) {
            City::create([
                'name' => $city,
                'country' => 'Georgia'
            ]);
        }
    }
}

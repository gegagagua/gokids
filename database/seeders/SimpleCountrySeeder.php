<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Country;

class SimpleCountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            [
                'name' => 'United States',
                'phone_index' => '+1',
                'currency' => 'USD',
                'garden_percent' => 0.00,
                'tariff' => 10.00,
                'price' => 10.00,
                'exchange_rate' => null,
                'dister' => null,
                'language' => 'en',
            ],
            [
                'name' => 'United Kingdom',
                'phone_index' => '+44',
                'currency' => 'GBP',
                'garden_percent' => 0.00,
                'tariff' => 10.00,
                'price' => 10.00,
                'exchange_rate' => null,
                'dister' => null,
                'language' => 'en',
            ],
            [
                'name' => 'Germany',
                'phone_index' => '+49',
                'currency' => 'EUR',
                'garden_percent' => 0.00,
                'tariff' => 10.00,
                'price' => 10.00,
                'exchange_rate' => null,
                'dister' => null,
                'language' => 'de',
            ],
            [
                'name' => 'France',
                'phone_index' => '+33',
                'currency' => 'EUR',
                'garden_percent' => 0.00,
                'tariff' => 10.00,
                'price' => 10.00,
                'exchange_rate' => null,
                'dister' => null,
                'language' => 'fr',
            ],
            [
                'name' => 'Georgia',
                'phone_index' => '+995',
                'currency' => 'GEL',
                'garden_percent' => 15.00,
                'tariff' => 0.00,
                'price' => 10.00,
                'exchange_rate' => 2.75,
                'dister' => null,
                'language' => 'ka',
            ],
        ];

        foreach ($countries as $countryData) {
            Country::updateOrCreate(
                ['name' => $countryData['name']],
                $countryData
            );
        }
    }
}

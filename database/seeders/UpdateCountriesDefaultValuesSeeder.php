<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Country;

class UpdateCountriesDefaultValuesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Update all countries with default values
        Country::query()->update([
            'currency' => 'USD',
            'garden_percent' => 5.00,
            'language' => 'en',
            'tariff' => 10.00,
            'exchange_rate' => 1.00,
            'sms_gateway_id' => 1, // Default SMS gateway
        ]);

        echo "Updated all countries with default values:\n";
        echo "- Currency: USD\n";
        echo "- Garden percent: 5%\n";
        echo "- Language: en\n";
        echo "- Tariff: 10\n";
        echo "- Exchange rate: 1.00\n";
    }
}

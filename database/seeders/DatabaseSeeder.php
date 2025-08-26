<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\GardenSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // CitySeeder::class,
            // GardenSeeder::class,
            // GardenGroupSeeder::class,
            // CardSeeder::class,
            // ParentModelSeeder::class,
            PersonTypeSeeder::class,
            CountrySeeder::class,
            DisterSeeder::class,
            SmsGatewaySeeder::class,
            PaymentGatewaySeeder::class
        ]);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\GardenSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GardenSeeder::class,
        ]);
    }
}

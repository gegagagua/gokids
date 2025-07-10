<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PersonType;

class PersonTypeSeeder extends Seeder
{
    public function run()
    {
        $types = ['დედა', 'მამა', 'ძიძა', 'და', 'ძმა', 'ბიძა', 'მამიდა', 'ბებია', 'ბაბუა'];

        foreach ($types as $type) {
            PersonType::firstOrCreate(['name' => $type]);
        }
    }
}
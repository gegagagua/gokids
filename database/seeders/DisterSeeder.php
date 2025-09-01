<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Dister;
use Illuminate\Support\Facades\Hash;

class DisterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Dister::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+995599123456',
            'password' => Hash::make('password123'),
            'country_id' => 1, // Georgia
            'gardens' => [1, 2, 3], // Sample garden IDs
        ]);

        Dister::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'phone' => '+995599654321',
            'password' => Hash::make('password123'),
            'country_id' => 1, // Georgia
            'gardens' => [4, 5], // Sample garden IDs
        ]);

        Dister::create([
            'first_name' => 'გიორგი',
            'last_name' => 'დავითაშვილი',
            'email' => 'giorgi@example.com',
            'phone' => '+995599789012',
            'password' => Hash::make('password123'),
            'country_id' => 1, // Georgia
            'gardens' => [1, 6, 7], // Sample garden IDs
        ]);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentGatewaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if records already exist
        if (DB::table('payment_gateways')->count() === 0) {
            DB::table('payment_gateways')->insert([
                [
                    'id' => 1,
                    'name' => 'BOG',
                    'currency' => 'GEL',
                    'base_url' => 'https://api.bog.ge/v1/payment',
                    'config' => json_encode([
                        'merchant_id' => '',
                        'api_key' => ''
                    ]),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => 2,
                    'name' => 'BOG - USD',
                    'currency' => 'USD',
                    'base_url' => 'https://api.bog.ge/v1/payment',
                    'config' => json_encode([
                        'merchant_id' => '',
                        'api_key' => ''
                    ]),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => 3,
                    'name' => 'BOG - EUR',
                    'currency' => 'EUR',
                    'base_url' => 'https://api.bog.ge/v1/payment',
                    'config' => json_encode([
                        'merchant_id' => '',
                        'api_key' => ''
                    ]),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }
}

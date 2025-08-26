<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SmsGatewaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if records already exist
        if (DB::table('sms_gateways')->count() === 0) {
            DB::table('sms_gateways')->insert([
                [
                    'id' => 1,
                    'name' => 'Geo Sms - ubill',
                    'base_url' => 'https://api.ubill.dev/v1/sms/send',
                    'config' => json_encode([
                        'api_key' => '706ee5fb74ece6ddd994e0905c1141fc791bae17',
                        'brand_id' => 1
                    ]),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => 2,
                    'name' => 'Global Intergo',
                    'base_url' => 'https://api.intergo.com/v1/sms/send',
                    'config' => json_encode([
                        'api_key' => '',
                        'brand_id' => 1
                    ]),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }
}

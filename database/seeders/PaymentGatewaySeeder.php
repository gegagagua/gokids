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
        // Add BOG - USD and BOG - EUR payment gateways
        $paymentGateways = [
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
        ];

        foreach ($paymentGateways as $gateway) {
            DB::table('payment_gateways')->updateOrInsert(
                ['id' => $gateway['id']],
                $gateway
            );
        }

        echo "✅ Added payment gateways:\n";
        echo "   - BOG - USD (ID: 2, Currency: USD)\n";
        echo "   - BOG - EUR (ID: 3, Currency: EUR)\n";
    }
}
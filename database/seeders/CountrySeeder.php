<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Country;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            // Major Countries
            ['name' => 'United States', 'phone_index' => '+1', 'currency' => 'USD'],
            ['name' => 'United Kingdom', 'phone_index' => '+44', 'currency' => 'USD'],
            ['name' => 'Germany', 'phone_index' => '+49', 'currency' => 'USD'],
            ['name' => 'France', 'phone_index' => '+33', 'currency' => 'USD'],
            ['name' => 'Italy', 'phone_index' => '+39', 'currency' => 'USD'],
            ['name' => 'Spain', 'phone_index' => '+34', 'currency' => 'USD'],
            ['name' => 'Canada', 'phone_index' => '+1', 'currency' => 'USD'],
            ['name' => 'Australia', 'phone_index' => '+61', 'currency' => 'USD'],
            ['name' => 'Japan', 'phone_index' => '+81', 'currency' => 'USD'],
            ['name' => 'China', 'phone_index' => '+86', 'currency' => 'USD'],
            ['name' => 'India', 'phone_index' => '+91', 'currency' => 'USD'],
            ['name' => 'Brazil', 'phone_index' => '+55', 'currency' => 'USD'],
            ['name' => 'Mexico', 'phone_index' => '+52', 'currency' => 'USD'],
            ['name' => 'South Korea', 'phone_index' => '+82', 'currency' => 'USD'],
            
            // European Countries
            ['name' => 'Netherlands', 'phone_index' => '+31', 'currency' => 'USD'],
            ['name' => 'Sweden', 'phone_index' => '+46', 'currency' => 'USD'],
            ['name' => 'Norway', 'phone_index' => '+47', 'currency' => 'USD'],
            ['name' => 'Denmark', 'phone_index' => '+45', 'currency' => 'USD'],
            ['name' => 'Finland', 'phone_index' => '+358', 'currency' => 'USD'],
            ['name' => 'Poland', 'phone_index' => '+48', 'currency' => 'USD'],
            ['name' => 'Czech Republic', 'phone_index' => '+420', 'currency' => 'USD'],
            ['name' => 'Hungary', 'phone_index' => '+36', 'currency' => 'USD'],
            ['name' => 'Romania', 'phone_index' => '+40', 'currency' => 'USD'],
            ['name' => 'Bulgaria', 'phone_index' => '+359', 'currency' => 'USD'],
            ['name' => 'Greece', 'phone_index' => '+30', 'currency' => 'USD'],
            ['name' => 'Portugal', 'phone_index' => '+351', 'currency' => 'USD'],
            ['name' => 'Ireland', 'phone_index' => '+353', 'currency' => 'USD'],
            ['name' => 'Austria', 'phone_index' => '+43', 'currency' => 'USD'],
            ['name' => 'Switzerland', 'phone_index' => '+41', 'currency' => 'USD'],
            ['name' => 'Belgium', 'phone_index' => '+32', 'currency' => 'USD'],
            ['name' => 'Luxembourg', 'phone_index' => '+352', 'currency' => 'USD'],
            
            // Middle East & Asia
            ['name' => 'Israel', 'phone_index' => '+972', 'currency' => 'USD'],
            ['name' => 'Saudi Arabia', 'phone_index' => '+966', 'currency' => 'USD'],
            ['name' => 'United Arab Emirates', 'phone_index' => '+971', 'currency' => 'USD'],
            ['name' => 'Egypt', 'phone_index' => '+20', 'currency' => 'USD'],
            ['name' => 'Turkey', 'phone_index' => '+90', 'currency' => 'USD'],
            ['name' => 'Iran', 'phone_index' => '+98', 'currency' => 'USD'],
            ['name' => 'Iraq', 'phone_index' => '+964', 'currency' => 'USD'],
            ['name' => 'Jordan', 'phone_index' => '+962', 'currency' => 'USD'],
            ['name' => 'Lebanon', 'phone_index' => '+961', 'currency' => 'USD'],
            ['name' => 'Kuwait', 'phone_index' => '+965', 'currency' => 'USD'],
            ['name' => 'Qatar', 'phone_index' => '+974', 'currency' => 'USD'],
            ['name' => 'Bahrain', 'phone_index' => '+973', 'currency' => 'USD'],
            ['name' => 'Oman', 'phone_index' => '+968', 'currency' => 'USD'],
            
            // Africa
            ['name' => 'South Africa', 'phone_index' => '+27', 'currency' => 'USD'],
            ['name' => 'Nigeria', 'phone_index' => '+234', 'currency' => 'USD'],
            ['name' => 'Kenya', 'phone_index' => '+254', 'currency' => 'USD'],
            ['name' => 'Morocco', 'phone_index' => '+212', 'currency' => 'USD'],
            ['name' => 'Algeria', 'phone_index' => '+213', 'currency' => 'USD'],
            ['name' => 'Tunisia', 'phone_index' => '+216', 'currency' => 'USD'],
            ['name' => 'Ghana', 'phone_index' => '+233', 'currency' => 'USD'],
            ['name' => 'Ethiopia', 'phone_index' => '+251', 'currency' => 'USD'],
            ['name' => 'Uganda', 'phone_index' => '+256', 'currency' => 'USD'],
            ['name' => 'Tanzania', 'phone_index' => '+255', 'currency' => 'USD'],
            
            // South America
            ['name' => 'Argentina', 'phone_index' => '+54', 'currency' => 'USD'],
            ['name' => 'Chile', 'phone_index' => '+56', 'currency' => 'USD'],
            ['name' => 'Colombia', 'phone_index' => '+57', 'currency' => 'USD'],
            ['name' => 'Peru', 'phone_index' => '+51', 'currency' => 'USD'],
            ['name' => 'Venezuela', 'phone_index' => '+58', 'currency' => 'USD'],
            ['name' => 'Ecuador', 'phone_index' => '+593', 'currency' => 'USD'],
            ['name' => 'Bolivia', 'phone_index' => '+591', 'currency' => 'USD'],
            ['name' => 'Paraguay', 'phone_index' => '+595', 'currency' => 'USD'],
            ['name' => 'Uruguay', 'phone_index' => '+598', 'currency' => 'USD'],
            ['name' => 'Guyana', 'phone_index' => '+592', 'currency' => 'USD'],
            ['name' => 'Suriname', 'phone_index' => '+597', 'currency' => 'USD'],
            
            // Asia Pacific
            ['name' => 'Thailand', 'phone_index' => '+66', 'currency' => 'USD'],
            ['name' => 'Vietnam', 'phone_index' => '+84', 'currency' => 'USD'],
            ['name' => 'Indonesia', 'phone_index' => '+62', 'currency' => 'USD'],
            ['name' => 'Malaysia', 'phone_index' => '+60', 'currency' => 'USD'],
            ['name' => 'Singapore', 'phone_index' => '+65', 'currency' => 'USD'],
            ['name' => 'Philippines', 'phone_index' => '+63', 'currency' => 'USD'],
            ['name' => 'New Zealand', 'phone_index' => '+64', 'currency' => 'USD'],
            ['name' => 'Bangladesh', 'phone_index' => '+880', 'currency' => 'USD'],
            ['name' => 'Pakistan', 'phone_index' => '+92', 'currency' => 'USD'],
            ['name' => 'Sri Lanka', 'phone_index' => '+94', 'currency' => 'USD'],
            ['name' => 'Nepal', 'phone_index' => '+977', 'currency' => 'USD'],
            ['name' => 'Myanmar', 'phone_index' => '+95', 'currency' => 'USD'],
            ['name' => 'Cambodia', 'phone_index' => '+855', 'currency' => 'USD'],
            ['name' => 'Laos', 'phone_index' => '+856', 'currency' => 'USD'],
            ['name' => 'Mongolia', 'phone_index' => '+976', 'currency' => 'USD'],
            
            // Eastern Europe & Caucasus
            ['name' => 'Georgia', 'phone_index' => '+995', 'currency' => 'USD'],
            ['name' => 'Armenia', 'phone_index' => '+374', 'currency' => 'USD'],
            ['name' => 'Azerbaijan', 'phone_index' => '+994', 'currency' => 'USD'],
            ['name' => 'Ukraine', 'phone_index' => '+380', 'currency' => 'USD'],
            ['name' => 'Belarus', 'phone_index' => '+375', 'currency' => 'USD'],
            ['name' => 'Moldova', 'phone_index' => '+373', 'currency' => 'USD'],
            ['name' => 'Lithuania', 'phone_index' => '+370', 'currency' => 'USD'],
            ['name' => 'Latvia', 'phone_index' => '+371', 'currency' => 'USD'],
            ['name' => 'Estonia', 'phone_index' => '+372', 'currency' => 'USD'],
            ['name' => 'Slovakia', 'phone_index' => '+421', 'currency' => 'USD'],
            ['name' => 'Slovenia', 'phone_index' => '+386', 'currency' => 'USD'],
            ['name' => 'Croatia', 'phone_index' => '+385', 'currency' => 'USD'],
            ['name' => 'Serbia', 'phone_index' => '+381', 'currency' => 'USD'],
            ['name' => 'Bosnia and Herzegovina', 'phone_index' => '+387', 'currency' => 'USD'],
            ['name' => 'Montenegro', 'phone_index' => '+382', 'currency' => 'USD'],
            ['name' => 'North Macedonia', 'phone_index' => '+389', 'currency' => 'USD'],
            ['name' => 'Albania', 'phone_index' => '+355', 'currency' => 'USD'],
            ['name' => 'Kosovo', 'phone_index' => '+383', 'currency' => 'USD'],
            
            // Central America & Caribbean
            ['name' => 'Costa Rica', 'phone_index' => '+506', 'currency' => 'USD'],
            ['name' => 'Panama', 'phone_index' => '+507', 'currency' => 'USD'],
            ['name' => 'Guatemala', 'phone_index' => '+502', 'currency' => 'USD'],
            ['name' => 'Honduras', 'phone_index' => '+504', 'currency' => 'USD'],
            ['name' => 'El Salvador', 'phone_index' => '+503', 'currency' => 'USD'],
            ['name' => 'Nicaragua', 'phone_index' => '+505', 'currency' => 'USD'],
            ['name' => 'Belize', 'phone_index' => '+501', 'currency' => 'USD'],
            ['name' => 'Jamaica', 'phone_index' => '+1', 'currency' => 'USD'],
            ['name' => 'Cuba', 'phone_index' => '+53', 'currency' => 'USD'],
            ['name' => 'Haiti', 'phone_index' => '+509', 'currency' => 'USD'],
            ['name' => 'Dominican Republic', 'phone_index' => '+1', 'currency' => 'USD'],
            ['name' => 'Puerto Rico', 'phone_index' => '+1', 'currency' => 'USD'],
            
            // Additional Countries
            ['name' => 'Iceland', 'phone_index' => '+354', 'currency' => 'USD'],
            ['name' => 'Malta', 'phone_index' => '+356', 'currency' => 'USD'],
            ['name' => 'Cyprus', 'phone_index' => '+357', 'currency' => 'USD'],
            ['name' => 'Monaco', 'phone_index' => '+377', 'currency' => 'USD'],
            ['name' => 'Liechtenstein', 'phone_index' => '+423', 'currency' => 'USD'],
            ['name' => 'San Marino', 'phone_index' => '+378', 'currency' => 'USD'],
            ['name' => 'Vatican City', 'phone_index' => '+39', 'currency' => 'USD'],
            ['name' => 'Andorra', 'phone_index' => '+376', 'currency' => 'USD'],
        ];

        foreach ($countries as $countryData) {
            Country::updateOrCreate(
                ['name' => $countryData['name']],
                [
                    'phone_index' => $countryData['phone_index'],
                    'currency' => $countryData['currency'],
                    'garden_percent' => 5.00,
                    'exchange_rate' => 1.00,
                    'sms_gateway_id' => 2,
                    'language' => 'en',
                    'tariff' => 10.00,
                    'price' => 10.00,
                ]
            );
        }

        echo "✅ Seeded " . count($countries) . " countries with:\n";
        echo "   - Currency: USD\n";
        echo "   - Garden percent: 5.00%\n";
        echo "   - Exchange rate: 1.00\n";
        echo "   - SMS Gateway ID: 2\n";
        echo "   - Language: en\n";
        echo "   - Tariff: 10.00\n";
        echo "   - Price: 10.00\n";
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Country;

class UpdateCountryPhoneIndexesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countryPhoneIndexes = [
            'United States' => '+1',
            'United Kingdom' => '+44',
            'Germany' => '+49',
            'France' => '+33',
            'Italy' => '+39',
            'Spain' => '+34',
            'Canada' => '+1',
            'Australia' => '+61',
            'Japan' => '+81',
            'China' => '+86',
            'India' => '+91',
            'Brazil' => '+55',
            'Mexico' => '+52',
            'South Korea' => '+82',
            'Netherlands' => '+31',
            'Sweden' => '+46',
            'Norway' => '+47',
            'Denmark' => '+45',
            'Finland' => '+358',
            'Poland' => '+48',
            'Czech Republic' => '+420',
            'Hungary' => '+36',
            'Romania' => '+40',
            'Bulgaria' => '+359',
            'Greece' => '+30',
            'Portugal' => '+351',
            'Ireland' => '+353',
            'Austria' => '+43',
            'Switzerland' => '+41',
            'Belgium' => '+32',
            'Luxembourg' => '+352',
            'Israel' => '+972',
            'Saudi Arabia' => '+966',
            'United Arab Emirates' => '+971',
            'Egypt' => '+20',
            'South Africa' => '+27',
            'Nigeria' => '+234',
            'Kenya' => '+254',
            'Argentina' => '+54',
            'Chile' => '+56',
            'Colombia' => '+57',
            'Peru' => '+51',
            'Venezuela' => '+58',
            'Thailand' => '+66',
            'Vietnam' => '+84',
            'Indonesia' => '+62',
            'Malaysia' => '+60',
            'Singapore' => '+65',
            'Philippines' => '+63',
            'New Zealand' => '+64',
        ];

        foreach ($countryPhoneIndexes as $countryName => $phoneIndex) {
            Country::where('name', $countryName)->update(['phone_index' => $phoneIndex]);
        }

        $this->command->info('Phone indexes updated for ' . count($countryPhoneIndexes) . ' countries.');
    }
}
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, get the current dister values and convert them to referral codes
        $countries = DB::table('countries')->whereNotNull('dister')->get();
        
        // Store the mapping of ID to referral code
        $disterMapping = [];
        foreach ($countries as $country) {
            $dister = DB::table('disters')->where('id', $country->dister)->first();
            if ($dister && $dister->referral) {
                $disterMapping[$country->id] = $dister->referral;
            }
        }
        
        // Drop the foreign key constraint first
        Schema::table('countries', function (Blueprint $table) {
            $table->dropForeign(['dister']);
        });
        
        // Change the column type to varchar
        Schema::table('countries', function (Blueprint $table) {
            $table->string('dister', 10)->nullable()->change();
        });
        
        // Update the values with referral codes
        foreach ($disterMapping as $countryId => $referralCode) {
            DB::table('countries')->where('id', $countryId)->update(['dister' => $referralCode]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert referral codes back to IDs
        $countries = DB::table('countries')->whereNotNull('dister')->get();
        
        $referralMapping = [];
        foreach ($countries as $country) {
            $dister = DB::table('disters')->where('referral', $country->dister)->first();
            if ($dister) {
                $referralMapping[$country->id] = $dister->id;
            }
        }
        
        // Change back to bigint
        Schema::table('countries', function (Blueprint $table) {
            $table->bigInteger('dister')->unsigned()->nullable()->change();
        });
        
        // Update the values with IDs
        foreach ($referralMapping as $countryId => $disterId) {
            DB::table('countries')->where('id', $countryId)->update(['dister' => $disterId]);
        }
        
        // Recreate the foreign key constraint
        Schema::table('countries', function (Blueprint $table) {
            $table->foreign('dister')->references('id')->on('disters');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->string('currency', 10)->default('GEL')->after('name');
        });

        // Set default currencies for existing countries
        \App\Models\Country::where('name', 'Georgia')->update(['currency' => 'GEL']);
        \App\Models\Country::where('name', 'United States')->update(['currency' => 'USD']);
        \App\Models\Country::where('name', 'United Kingdom')->update(['currency' => 'GBP']);
        \App\Models\Country::where('name', 'European Union')->update(['currency' => 'EUR']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};

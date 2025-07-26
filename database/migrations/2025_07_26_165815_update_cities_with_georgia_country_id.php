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
        // Get Georgia's country ID
        $georgia = \App\Models\Country::where('name', 'საქართველო')->first();
        
        if ($georgia) {
            // Update all existing cities to have Georgia as their country
            \App\Models\City::query()->update(['country_id' => $georgia->id]);
            
            // Add foreign key constraint
            Schema::table('cities', function (Blueprint $table) {
                $table->foreign('country_id')->references('id')->on('countries')->onDelete('restrict');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
        });
    }
};

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
            
            // Note: Foreign key constraint is assumed to already exist
            // If it doesn't exist, it should be added manually or through a separate migration
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed for data updates
        // Foreign key constraint should be handled separately if needed
    }
};

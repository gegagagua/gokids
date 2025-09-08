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
        Schema::table('gardens', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['country']);
            // Rename the column
            $table->renameColumn('country', 'country_id');
        });
        
        // Recreate the foreign key constraint with the new column name
        Schema::table('gardens', function (Blueprint $table) {
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gardens', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['country_id']);
            // Rename the column back
            $table->renameColumn('country_id', 'country');
        });
        
        // Recreate the foreign key constraint with the original column name
        Schema::table('gardens', function (Blueprint $table) {
            $table->foreign('country')->references('id')->on('countries')->onDelete('set null');
        });
    }
};

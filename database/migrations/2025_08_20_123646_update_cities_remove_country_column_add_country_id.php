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
        Schema::table('cities', function (Blueprint $table) {
            // Check if country_id column doesn't exist and add it
            if (!Schema::hasColumn('cities', 'country_id')) {
                $table->unsignedBigInteger('country_id')->nullable()->after('name');
                $table->foreign('country_id')->references('id')->on('countries')->onDelete('set null');
            }
            
            // Check if country column exists and remove it
            if (Schema::hasColumn('cities', 'country')) {
                $table->dropColumn('country');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table) {
            // Add back country column
            if (!Schema::hasColumn('cities', 'country')) {
                $table->string('country')->nullable()->after('name');
            }
            
            // Remove country_id column and foreign key
            if (Schema::hasColumn('cities', 'country_id')) {
                $table->dropForeign(['country_id']);
                $table->dropColumn('country_id');
            }
        });
    }
};

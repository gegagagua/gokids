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
        Schema::table('cards', function (Blueprint $table) {
            // Add parent verification field (boolean)
            $table->boolean('parent_verification')->default(false)->after('image_path');
            
            // Add license field (can be boolean or date)
            // Using JSON to store either boolean or date
            $table->json('license')->nullable()->after('parent_verification');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['parent_verification', 'license']);
        });
    }
};

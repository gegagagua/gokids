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
        Schema::table('devices', function (Blueprint $table) {
            // Add platform column to track iOS vs Android devices
            // iOS will receive dismiss notifications, Android won't (Android limitation)
            // Defaults to 'android' for backward compatibility with existing devices
            $table->enum('platform', ['ios', 'android'])->default('android')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('platform');
        });
    }
};

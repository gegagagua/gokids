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
        Schema::table('notifications', function (Blueprint $table) {
            // Add 'canceled' to status enum
            DB::statement("ALTER TABLE notifications MODIFY COLUMN status ENUM('pending', 'sent', 'failed', 'accepted', 'canceled') DEFAULT 'pending'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Revert status enum to original values
            DB::statement("ALTER TABLE notifications MODIFY COLUMN status ENUM('pending', 'sent', 'failed', 'accepted') DEFAULT 'pending'");
        });
    }
};

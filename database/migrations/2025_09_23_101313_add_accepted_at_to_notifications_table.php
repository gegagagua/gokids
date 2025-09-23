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
        Schema::table('notifications', function (Blueprint $table) {
            // Add accepted_at timestamp column
            $table->timestamp('accepted_at')->nullable()->after('sent_at');
            
            // Add 'accepted' to status enum
            $table->enum('status', ['pending', 'sent', 'failed', 'accepted'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Remove accepted_at column
            $table->dropColumn('accepted_at');
            
            // Revert status enum to original values
            $table->enum('status', ['pending', 'sent', 'failed'])->change();
        });
    }
};

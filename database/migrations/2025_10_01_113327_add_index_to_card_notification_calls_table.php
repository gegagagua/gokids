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
        Schema::table('card_notification_calls', function (Blueprint $table) {
            // Check if index doesn't exist before creating it
            if (!Schema::hasIndex('card_notification_calls', 'card_notif_calls_idx')) {
                $table->index(['card_id', 'notification_type', 'called_at'], 'card_notif_calls_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('card_notification_calls', function (Blueprint $table) {
            $table->dropIndex('card_notif_calls_idx');
        });
    }
};
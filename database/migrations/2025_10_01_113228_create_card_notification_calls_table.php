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
        Schema::create('card_notification_calls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('card_id');
            $table->string('notification_type')->default('card_to_all_devices'); // Type of notification call
            $table->timestamp('called_at');
            $table->timestamps();

            $table->foreign('card_id')->references('id')->on('cards')->onDelete('cascade');
            $table->index(['card_id', 'notification_type', 'called_at'], 'card_notif_calls_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_notification_calls');
    }
};
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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->string('expo_token');
            $table->unsignedBigInteger('device_id');
            $table->unsignedBigInteger('card_id')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
            $table->foreign('card_id')->references('id')->on('cards')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

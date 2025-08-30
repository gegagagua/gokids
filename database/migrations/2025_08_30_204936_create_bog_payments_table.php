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
        Schema::create('bog_payments', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique();
            $table->string('bog_transaction_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('GEL');
            $table->string('status')->default('pending'); // pending, processing, completed, failed, cancelled
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('card_id')->nullable();
            $table->unsignedBigInteger('garden_id')->nullable();
            $table->string('payment_method')->nullable(); // card, saved_card, subscription
            $table->string('saved_card_id')->nullable(); // BOG saved card ID
            $table->json('payment_details')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('card_id')->references('id')->on('cards')->onDelete('set null');
            $table->foreign('garden_id')->references('id')->on('gardens')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bog_payments');
    }
};

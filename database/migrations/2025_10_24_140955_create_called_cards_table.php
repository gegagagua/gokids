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
        Schema::create('called_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('card_id');
            $table->timestamp('create_date')->useCurrent();
            $table->timestamps();
            
            // Foreign key constraint
            $table->foreign('card_id')->references('id')->on('cards')->onDelete('cascade');
            
            // Index for better performance
            $table->index('card_id');
            $table->index('create_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('called_cards');
    }
};

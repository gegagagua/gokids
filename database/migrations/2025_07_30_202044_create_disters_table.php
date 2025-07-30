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
        Schema::create('disters', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->string('first_name');
            $table->string('last_name');
            $table->unsignedBigInteger('country_id');
            $table->unsignedBigInteger('city_id');
            $table->json('gardens')->nullable(); // Array of garden IDs
            $table->rememberToken();
            $table->timestamps();
            
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disters');
    }
};

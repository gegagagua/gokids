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
        Schema::create('garden_images', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedBigInteger('garden_id');
            $table->string('image');
            $table->timestamps();

            $table->foreign('garden_id')->references('id')->on('gardens')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('garden_images');
    }
};

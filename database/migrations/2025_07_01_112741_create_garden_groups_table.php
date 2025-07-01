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
        Schema::create('garden_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('garden_id');
            $table->foreign('garden_id')->references('id')->on('gardens')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('garden_groups');
    }
};

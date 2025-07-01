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
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->string('child_first_name');
            $table->string('child_last_name');
            $table->string('father_name');
            $table->string('parent_first_name');
            $table->string('parent_last_name');
            $table->string('phone');
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('group_id');
            $table->foreign('group_id')->references('id')->on('garden_groups')->onDelete('cascade');
            $table->string('parent_code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};

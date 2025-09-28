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
        Schema::table('garden_images', function (Blueprint $table) {
            $table->integer('index')->default(0)->after('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('garden_images', function (Blueprint $table) {
            $table->dropColumn('index');
        });
    }
};

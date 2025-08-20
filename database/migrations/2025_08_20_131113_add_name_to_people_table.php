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
        Schema::table('people', function (Blueprint $table) {
            // Add name column
            $table->string('name')->after('id');
        });

        // Combine first_name and last_name into name
        DB::statement('UPDATE people SET name = CONCAT(first_name, " ", last_name)');

        // Drop old columns
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            // Add back old columns
            $table->string('first_name')->after('id');
            $table->string('last_name')->after('first_name');
        });

        // Split name back into first_name and last_name
        DB::statement('UPDATE people SET first_name = SUBSTRING_INDEX(name, " ", 1), last_name = SUBSTRING_INDEX(name, " ", -1)');

        // Drop name column
        $table->dropColumn('name');
    }
};

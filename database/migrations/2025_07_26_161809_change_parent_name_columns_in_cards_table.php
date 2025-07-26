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
        Schema::table('cards', function (Blueprint $table) {
            // Drop the old columns
            $table->dropColumn(['parent_first_name', 'parent_last_name']);
            
            // Add the new parent_name column
            $table->string('parent_name')->after('father_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            // Drop the new column
            $table->dropColumn('parent_name');
            
            // Add back the old columns
            $table->string('parent_first_name')->after('father_name');
            $table->string('parent_last_name')->after('parent_first_name');
        });
    }
};

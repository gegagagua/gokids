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
        Schema::table('disters', function (Blueprint $table) {
            $table->decimal('second_percent', 5, 2)->nullable()->after('percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disters', function (Blueprint $table) {
            $table->dropColumn('second_percent');
        });
    }
};

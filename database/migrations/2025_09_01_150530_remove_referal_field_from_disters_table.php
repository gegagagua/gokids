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
        // drop მხოლოდ მაშინ, თუ სვეტი მართლაც არსებობს
        if (Schema::hasColumn('disters', 'referal')) {
            Schema::table('disters', function (Blueprint $table) {
                $table->dropColumn('referal');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // აღადგინე მხოლოდ მაშინ, თუ სვეტი არ არსებობს
        if (!Schema::hasColumn('disters', 'referal')) {
            Schema::table('disters', function (Blueprint $table) {
                $table->string('referal', 6)->nullable();
            });
        }
    }
};

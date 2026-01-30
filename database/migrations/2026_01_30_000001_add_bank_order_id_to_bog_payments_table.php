<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * BOG E-commerce: bank returns order id and password; we store for Get Order Details and callback lookup.
     */
    public function up(): void
    {
        Schema::table('bog_payments', function (Blueprint $table) {
            $table->string('bank_order_id')->nullable()->after('order_id');
            $table->string('bank_order_password')->nullable()->after('bank_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bog_payments', function (Blueprint $table) {
            $table->dropColumn(['bank_order_id', 'bank_order_password']);
        });
    }
};

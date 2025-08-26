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
        // Columns already exist, just add foreign key constraints
        Schema::table('countries', function (Blueprint $table) {
            // Add foreign key constraints to existing columns
            $table->foreign('sms_gateway_id')->references('id')->on('sms_gateways')->onDelete('set null');
            $table->foreign('payment_gateway_id')->references('id')->on('payment_gateways')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropForeign(['sms_gateway_id']);
            $table->dropForeign(['payment_gateway_id']);
        });
    }
};

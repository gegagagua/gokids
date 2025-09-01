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
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->string('currency', 10)->default('GEL')->after('name');
        });

        // Set default currencies for existing payment gateways
        \App\Models\PaymentGateway::where('name', 'BOG')->update(['currency' => 'GEL']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};

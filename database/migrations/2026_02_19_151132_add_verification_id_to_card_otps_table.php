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
        Schema::table('card_otps', function (Blueprint $table) {
            $table->string('verification_id')->nullable()->after('otp');
            $table->tinyInteger('sms_gateway_id')->nullable()->after('verification_id');
        });
    }

    public function down(): void
    {
        Schema::table('card_otps', function (Blueprint $table) {
            $table->dropColumn(['verification_id', 'sms_gateway_id']);
        });
    }
};

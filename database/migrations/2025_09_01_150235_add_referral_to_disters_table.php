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
            $table->string('referral', 6)->nullable()->after('iban');
        });

        // Populate existing records with unique referral codes
        $disters = \App\Models\Dister::whereNull('referral')->get();
        foreach ($disters as $dister) {
            $dister->referral = \App\Models\Dister::generateUniqueReferralCode();
            $dister->save();
        }

        // Now add the unique constraint
        Schema::table('disters', function (Blueprint $table) {
            $table->string('referral', 6)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disters', function (Blueprint $table) {
            $table->dropColumn('referral');
        });
    }
};

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
            $table->string('first_name')->after('name');
            $table->string('last_name')->after('first_name');
        });

        // Populate existing records by splitting the name field
        $disters = \App\Models\Dister::all();
        foreach ($disters as $dister) {
            if ($dister->name) {
                $nameParts = explode(' ', $dister->name, 2);
                $dister->first_name = $nameParts[0] ?? '';
                $dister->last_name = $nameParts[1] ?? '';
                $dister->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disters', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};

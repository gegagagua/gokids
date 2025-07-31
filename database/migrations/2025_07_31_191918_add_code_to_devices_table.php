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
        // Update existing devices with unique codes if they don't have one
        $devices = \App\Models\Device::whereNull('code')->get();
        foreach ($devices as $device) {
            $device->code = \App\Models\Device::generateDeviceCode();
            $device->save();
        }
        
        // Add unique constraint to existing code column
        Schema::table('devices', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};

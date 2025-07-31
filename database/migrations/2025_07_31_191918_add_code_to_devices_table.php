<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ჯერ დაამატე code სვეტი (თუ არ არსებობს)
        if (!Schema::hasColumn('devices', 'code')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->string('code', 6)->nullable()->after('name');
            });
        }

        // 2. განაახლე ყველა device-ს კოდი, სადაც code არის null ან ცარიელი
        $devices = \App\Models\Device::whereNull('code')->orWhere('code', '')->get();
        foreach ($devices as $device) {
            $device->code = \App\Models\Device::generateDeviceCode();
            $device->save();
        }

        // 3. ბოლოს დაამატე უნიკალური constraint-ი
        Schema::table('devices', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};

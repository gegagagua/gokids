<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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

        // 2. განაახლე ყველა device-ს კოდი, სადაც code არის null ან ცარიელი (raw SQL-ით)
        $devices = DB::table('devices')->whereNull('code')->orWhere('code', '')->get();
        foreach ($devices as $device) {
            $code = $this->generateDeviceCode();
            DB::table('devices')->where('id', $device->id)->update(['code' => $code]);
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

    /**
     * Generate a unique 6-character device code
     */
    private function generateDeviceCode()
    {
        do {
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $code = '';
            
            for ($i = 0; $i < 6; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (DB::table('devices')->where('code', $code)->exists());

        return $code;
    }
};

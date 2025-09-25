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
        Schema::table('devices', function (Blueprint $table) {
            $table->boolean('is_logged_in')->default(false)->after('active_garden_groups');
            $table->timestamp('last_login_at')->nullable()->after('is_logged_in');
            $table->string('session_token')->nullable()->after('last_login_at');
            $table->timestamp('session_expires_at')->nullable()->after('session_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['is_logged_in', 'last_login_at', 'session_token', 'session_expires_at']);
        });
    }
};

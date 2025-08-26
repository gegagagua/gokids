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
        // 1. Create sms_gateways table if it doesn't exist
        if (!Schema::hasTable('sms_gateways')) {
            Schema::create('sms_gateways', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('base_url');
                $table->json('config')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. Create payment_gateways table if it doesn't exist
        if (!Schema::hasTable('payment_gateways')) {
            Schema::create('payment_gateways', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('base_url');
                $table->json('config')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 3. Add gateway columns to countries table if they don't exist
        if (!Schema::hasColumn('countries', 'sms_gateway_id')) {
            Schema::table('countries', function (Blueprint $table) {
                $table->unsignedBigInteger('sms_gateway_id')->nullable()->after('dister');
            });
        }

        if (!Schema::hasColumn('countries', 'payment_gateway_id')) {
            Schema::table('countries', function (Blueprint $table) {
                $table->unsignedBigInteger('payment_gateway_id')->nullable()->after('sms_gateway_id');
            });
        }

        // 4. Add foreign key constraints
        Schema::table('countries', function (Blueprint $table) {
            // Drop existing foreign keys if they exist
            try {
                $table->dropForeign(['sms_gateway_id']);
            } catch (Exception $e) {
                // Foreign key doesn't exist, continue
            }
            
            try {
                $table->dropForeign(['payment_gateway_id']);
            } catch (Exception $e) {
                // Foreign key doesn't exist, continue
            }

            // Add new foreign keys
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

        Schema::dropIfExists('payment_gateways');
        Schema::dropIfExists('sms_gateways');
    }
};

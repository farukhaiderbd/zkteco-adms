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
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_name');
            $table->string('serial_number')->unique();
            $table->string('device_ip')->nullable();
            $table->enum('status', ['pending', 'online', 'offline', 'unauthorized', 'communicated'])->default('pending');
            $table->timestamp('last_online')->nullable();
            $table->timestamps();

            $table->index('serial_number');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biometric_devices');
    }
};

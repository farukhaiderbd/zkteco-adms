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
        Schema::create('biometric_device_attendances', function (Blueprint $table) {
            $table->id();
            $table->string('device_name');
            $table->string('device_serial_number');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('table')->nullable();
            $table->string('stamp')->nullable();
            $table->string('employee_id');
            $table->timestamp('timestamp');
            $table->integer('status1')->default(0);
            $table->integer('status2')->default(-1);
            $table->integer('status3')->default(-1);
            $table->integer('status4')->default(-1);
            $table->integer('status5')->default(-1);
            $table->timestamps();

            $table->index('device_serial_number');
            $table->index('employee_id');
            $table->index('user_id');
            $table->index('timestamp');
            $table->index('status1');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biometric_device_attendances');
    }
};

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
        Schema::create('biometric_commands', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // CREATEUSER, DELETEUSER, QUERYUSER
            $table->string('device_serial_number');
            $table->string('command_id');
            $table->longText('command');
            $table->string('employee_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('status', ['pending', 'sent', 'executed', 'failed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index('device_serial_number');
            $table->index('command_id');
            $table->index('employee_id');
            $table->index('user_id');
            $table->index('type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biometric_commands');
    }
};

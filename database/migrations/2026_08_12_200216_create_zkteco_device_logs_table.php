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
        Schema::create('zkteco_device_logs', function (Blueprint $table) {
            $table->id();
            $table->string('device_serial')->nullable()->index();
            $table->string('device_ip')->nullable();
            $table->string('endpoint')->nullable();
            $table->string('comm_type')->nullable();
            $table->enum('log_type', ['handshake', 'attendance', 'command_sent', 'command_result', 'ping', 'timeout', 'error', 'info'])->default('info');
            $table->text('message');
            $table->json('log_data')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            // Indexes for common queries
            $table->index('log_type');
            $table->index(['device_serial', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zkteco_device_logs');
    }
};

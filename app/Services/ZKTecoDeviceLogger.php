<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ZKTecoDeviceLogger
{
    protected $deviceSerial;

    protected $deviceIp;

    protected $endpoint;

    protected $commType;

    /**
     * Set device context for logging
     */
    public function setDeviceContext(string $deviceSerial, ?string $deviceIp = null): self
    {
        $this->deviceSerial = $deviceSerial;
        $this->deviceIp = $deviceIp;

        return $this;
    }

    /**
     * Set endpoint information
     */
    public function setEndpoint(string $endpoint): self
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    /**
     * Set communication type
     */
    public function setCommType(string $commType): self
    {
        $this->commType = $commType;

        return $this;
    }

    /**
     * Log device handshake
     */
    public function logHandshake(array $additionalData = []): void
    {
        $this->setCommType('handshake');
        $this->log('info', 'Device handshake received', $additionalData);
        $this->saveToDatabase('handshake', 'Device connected', $additionalData);
    }

    /**
     * Log attendance data received
     */
    public function logAttendanceData(int $recordCount, array $additionalData = []): void
    {
        $this->setCommType('attendance');
        $this->log('info', "Attendance data received - {$recordCount} records", $additionalData);
        $this->saveToDatabase('attendance', "Received {$recordCount} attendance records", $additionalData);
    }

    /**
     * Log command sent to device
     */
    public function logCommandSent(string $commandId, string $commandType, array $additionalData = []): void
    {
        $this->setCommType('command_sent');
        $this->log('info', "Command sent - ID: {$commandId}, Type: {$commandType}", $additionalData);
        $this->saveToDatabase('command_sent', "Sent {$commandType} command", array_merge($additionalData, ['command_id' => $commandId]));
    }

    /**
     * Log command result received
     */
    public function logCommandResult(string $commandId, string $result, array $additionalData = []): void
    {
        $this->setCommType('command_result');
        $this->log('info', "Command result received - ID: {$commandId}, Result: {$result}", $additionalData);
        $this->saveToDatabase('command_result', "Command {$result}", array_merge($additionalData, ['command_id' => $commandId, 'result' => $result]));
    }

    /**
     * Log device error
     */
    public function logError(string $message, array $additionalData = []): void
    {
        $this->log('error', $message, $additionalData);
        $this->saveToDatabase('error', $message, $additionalData);
    }

    /**
     * Log device ping
     */
    public function logPing(array $additionalData = []): void
    {
        $this->setCommType('ping');
        $this->log('debug', 'Device ping received', $additionalData);
        $this->saveToDatabase('ping', 'Device heartbeat', $additionalData);
    }

    /**
     * Log device timeout/no response
     */
    public function logTimeout(array $additionalData = []): void
    {
        $this->log('warning', 'Device communication timeout', $additionalData);
        $this->saveToDatabase('timeout', 'Device timeout', $additionalData);
    }

    /**
     * Generic log method
     */
    public function log(string $level, string $message, array $data = []): void
    {
        $context = array_filter([
            'device_serial' => $this->deviceSerial,
            'device_ip' => $this->deviceIp,
            'endpoint' => $this->endpoint,
            'comm_type' => $this->commType,
            'data' => $data,
        ]);

        Log::log($level, "[ZKTeco] {$message}", $context);
    }

    /**
     * Save log to database for dashboard viewing
     */
    protected function saveToDatabase(string $logType, string $message, array $data = []): void
    {
        try {
            DB::table('zkteco_device_logs')->insert([
                'device_serial' => $this->deviceSerial,
                'device_ip' => $this->deviceIp,
                'endpoint' => $this->endpoint,
                'comm_type' => $this->commType,
                'log_type' => $logType,
                'message' => $message,
                'log_data' => json_encode($data),
                'created_at' => Carbon::now(),
                'updated_at' => null,
            ]);
        } catch (\Exception $e) {
            // Fallback to file log if database fails
            Log::error('Failed to save ZKTeco log to database', [
                'error' => $e->getMessage(),
                'original_log' => $message,
            ]);
        }
    }

    /**
     * Create a logger instance for a specific request
     */
    public static function forRequest(string $deviceSerial, ?string $deviceIp = null): self
    {
        return new self()->setDeviceContext($deviceSerial, $deviceIp);
    }
}

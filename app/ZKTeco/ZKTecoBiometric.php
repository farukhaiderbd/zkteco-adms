<?php

namespace AhidTechnologies\ZKTecoBiometric;

use App\ZKTeco\Models\BiometricAttendance;
use App\ZKTeco\Models\BiometricCommand;
use App\ZKTeco\Models\BiometricDevice;
use App\ZKTeco\Models\BiometricEmployee;
use App\ZKTeco\Traits\HasLogging;

class ZKTecoBiometric
{
    use HasLogging;

    /**
     * Create a new biometric device.
     */
    public function createDevice(array $data): BiometricDevice
    {
        $this->logDatabaseOperation('CREATE', 'BiometricDevice', $data);

        return BiometricDevice::create($data);
    }

    /**
     * Get all devices.
     */
    public function getDevices()
    {
        return BiometricDevice::all();
    }

    /**
     * Get device by serial number.
     */
    public function getDeviceBySerial(string $serialNumber): ?BiometricDevice
    {
        return BiometricDevice::where('serial_number', $serialNumber)->first();
    }

    /**
     * Create a new biometric employee.
     */
    public function createEmployee(array $data): BiometricEmployee
    {
        $this->logDatabaseOperation('CREATE', 'BiometricEmployee', $data);

        return BiometricEmployee::create($data);
    }

    /**
     * Get employee by biometric ID.
     */
    public function getEmployeeByBiometricId(string $biometricId): ?BiometricEmployee
    {
        return BiometricEmployee::where('biometric_employee_id', $biometricId)->first();
    }

    /**
     * Send command to device.
     */
    public function sendCommand(string $deviceSerial, string $commandId, string $command, ?string $employeeId = null, ?int $userId = null): BiometricCommand
    {
        $commandData = [
            'device_serial_number' => $deviceSerial,
            'command_id' => $commandId,
            'command' => $command,
            'employee_id' => $employeeId,
            'user_id' => $userId,
            'status' => 'pending',
        ];

        $this->logInfo('Creating command for device', [
            'device_serial' => $deviceSerial,
            'command_id' => $commandId,
            'employee_id' => $employeeId,
        ]);

        $this->logDatabaseOperation('CREATE', 'BiometricCommand', $commandData);

        return BiometricCommand::create($commandData);
    }

    /**
     * Create user command for device.
     */
    public function createUserCommand(string $deviceSerial, string $pin, string $name, ?int $userId = null): BiometricCommand
    {
        $commandId = 'CREATEUSER-'.uniqid();
        $command = BiometricCommand::createUserCommand($commandId, $pin, $name);

        $this->logInfo('Creating user command for device', [
            'device_serial' => $deviceSerial,
            'command_id' => $commandId,
            'pin' => $pin,
            'name' => $name,
        ]);

        return $this->sendCommand($deviceSerial, $commandId, $command, $pin, $userId);
    }

    /**
     * Delete user command for device.
     */
    public function deleteUserCommand(string $deviceSerial, string $pin): BiometricCommand
    {
        $commandId = 'DELETEUSER-'.uniqid();
        $command = BiometricCommand::deleteUserCommand($commandId, $pin);

        $this->logInfo('Creating delete user command for device', [
            'device_serial' => $deviceSerial,
            'command_id' => $commandId,
            'pin' => $pin,
        ]);

        return $this->sendCommand($deviceSerial, $commandId, $command, $pin);
    }

    /**
     * Get attendance records.
     */
    public function getAttendance(?string $deviceSerial = null, ?string $employeeId = null, ?\DateTime $from = null, ?\DateTime $to = null)
    {
        $query = BiometricAttendance::query();

        if ($deviceSerial) {
            $query->where('device_serial_number', $deviceSerial);
        }

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        if ($from) {
            $query->where('timestamp', '>=', $from);
        }

        if ($to) {
            $query->where('timestamp', '<=', $to);
        }

        return $query->orderBy('timestamp', 'desc')->get();
    }

    /**
     * Get pending commands for device.
     */
    public function getPendingCommands(string $deviceSerial)
    {
        return BiometricCommand::where('device_serial_number', $deviceSerial)
            ->where('status', 'pending')
            ->get();
    }

    /**
     * Sync time for all devices or specific device
     */
    public function syncTime($deviceSerial = null): array
    {
        $currentTime = now()->format('Y-m-d H:i:s');
        $results = [];

        if ($deviceSerial) {
            $devices = BiometricDevice::where('serial_number', $deviceSerial)->get();
        } else {
            $devices = BiometricDevice::all();
        }

        foreach ($devices as $device) {
            try {
                $commandId = 'SYNCTIME_'.$device->serial_number.'_'.now()->timestamp;
                $command = sprintf('SET OPTIONS DateTime=%s', $currentTime);

                BiometricCommand::create([
                    'type' => 'SYNCTIME',
                    'device_serial_number' => $device->serial_number,
                    'command_id' => $commandId,
                    'command' => $command,
                    'employee_id' => null,
                    'user_id' => null,
                    'status' => 'pending',
                ]);

                $results[] = [
                    'device' => $device->serial_number,
                    'status' => 'success',
                    'message' => 'Time sync command queued',
                ];

                $this->logInfo('Manual time sync command queued', [
                    'device_sn' => $device->serial_number,
                    'command_id' => $commandId,
                    'sync_time' => $currentTime,
                ]);
            } catch (\Exception $e) {
                $results[] = [
                    'device' => $device->serial_number,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];

                $this->logError('Failed to queue time sync command', [
                    'device_sn' => $device->serial_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Get package version.
     */
    public function getVersion(): string
    {
        return '1.0.2';
    }
}

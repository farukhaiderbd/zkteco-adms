<?php

namespace App\Services;

use App\ZKTeco\Models\BiometricCommand;
use App\ZKTeco\Models\BiometricDevice;
use Illuminate\Support\Facades\Log;
use Syofyanzuhad\FilamentZktecoAdms\Models\Device;
use Syofyanzuhad\FilamentZktecoAdms\Models\DeviceCommand;
use Syofyanzuhad\FilamentZktecoAdms\Services\DeviceCommandBuilder;

class ZKTecoCommandService
{
    protected DeviceCommandBuilder $commandBuilder;

    public function __construct(DeviceCommandBuilder $commandBuilder)
    {
        $this->commandBuilder = $commandBuilder;
    }

    /**
     * Sync device from biometric_devices to zkteco_devices
     */
    public function syncDevice(string $serialNumber): ?Device
    {
        $biometricDevice = BiometricDevice::where('serial_number', $serialNumber)->first();

        if (! $biometricDevice) {
            Log::warning("Biometric device not found for sync: {$serialNumber}");

            return null;
        }

        $zktecoDevice = Device::where('serial_number', $serialNumber)->first();

        if (! $zktecoDevice) {
            // Create device in ZKTeco package table
            $zktecoDevice = Device::create([
                'serial_number' => $biometricDevice->serial_number,
                'name' => $biometricDevice->device_name,
                'ip_address' => $biometricDevice->device_ip,
                'status' => $biometricDevice->status === 'online' ? 'online' : 'offline',
                'last_activity_at' => $biometricDevice->last_online,
            ]);

            Log::info('Created ZKTeco device from biometric device', [
                'serial_number' => $serialNumber,
                'zkteco_device_id' => $zktecoDevice->id,
            ]);
        } else {
            // Update existing device
            $zktecoDevice->update([
                'name' => $biometricDevice->device_name,
                'ip_address' => $biometricDevice->device_ip,
                'status' => $biometricDevice->status === 'online' ? 'online' : 'offline',
                'last_activity_at' => $biometricDevice->last_online,
            ]);
        }

        return $zktecoDevice;
    }

    /**
     * Migrate pending commands from biometric_commands to zkteco_device_commands
     */
    public function migratePendingCommands(string $serialNumber): int
    {
        // First sync the device
        $zktecoDevice = $this->syncDevice($serialNumber);

        if (! $zktecoDevice) {
            return 0;
        }

        // Get pending commands from custom table
        $pendingCommands = BiometricCommand::where('device_serial_number', $serialNumber)
            ->where('status', 'pending')
            ->get();

        $migratedCount = 0;

        foreach ($pendingCommands as $biometricCommand) {
            try {
                $this->convertBiometricCommandToZkteco($biometricCommand, $zktecoDevice);

                // Mark the original command as sent to prevent duplicate migration
                $biometricCommand->update(['status' => 'sent']);
                $migratedCount++;

                Log::info('Migrated command to ZKTeco package', [
                    'original_command_id' => $biometricCommand->id,
                    'command_type' => $biometricCommand->type,
                    'device_serial' => $serialNumber,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to migrate command', [
                    'command_id' => $biometricCommand->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $migratedCount;
    }

    /**
     * Convert a biometric command to ZKTeco package command
     */
    protected function convertBiometricCommandToZkteco(BiometricCommand $biometricCommand, Device $zktecoDevice): void
    {
        $commandType = $biometricCommand->type;
        $commandContent = $biometricCommand->command;

        // Parse the command to extract user data if it's a user command
        if (strpos($commandContent, 'DATA USER PIN=') !== false) {
            $userData = $this->parseUserCommand($commandContent);
            $this->commandBuilder->addUser($zktecoDevice, $userData);
        } elseif (strpos($commandContent, 'DELETE USERINFO PIN=') !== false) {
            $pin = $this->parseDeleteUserCommand($commandContent);
            $this->commandBuilder->deleteUser($zktecoDevice, $pin);
        } elseif (strpos($commandContent, 'SET OPTIONS') !== false && strpos($commandContent, 'DateTime=') !== false) {
            $this->commandBuilder->syncTime($zktecoDevice);
        } elseif (strpos($commandContent, 'CLEAR LOG') !== false) {
            $this->commandBuilder->clearAttendanceLogs($zktecoDevice);
        } elseif (strpos($commandContent, 'REBOOT') !== false) {
            $this->commandBuilder->reboot($zktecoDevice);
        } elseif (strpos($commandContent, 'INFO') !== false) {
            $this->commandBuilder->info($zktecoDevice);
        } else {
            // Generic command - create directly
            $this->commandBuilder->createCommand(
                $zktecoDevice,
                $commandType,
                $commandContent
            );
        }
    }

    /**
     * Parse user command data
     */
    protected function parseUserCommand(string $command): array
    {
        $userData = [
            'pin' => '',
            'name' => '',
            'card' => '',
            'privilege' => 0,
        ];

        // Parse PIN=XXX\tName=YYY\tCard=ZZZ format
        if (preg_match('/PIN=([^\t\s]+)/', $command, $matches)) {
            $userData['pin'] = $matches[1];
        }

        if (preg_match('/Name=([^\t\s\r\n]+)/', $command, $matches)) {
            $userData['name'] = $matches[1];
        }

        if (preg_match('/Card=([^\t\s\r\n]+)/', $command, $matches)) {
            $userData['card'] = $matches[1];
        }

        return $userData;
    }

    /**
     * Parse delete user command to extract PIN
     */
    protected function parseDeleteUserCommand(string $command): string
    {
        if (preg_match('/PIN=([^\t\s\r\n]+)/', $command, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Create a new user command using the ZKTeco package
     */
    public function createUserCommand(string $serialNumber, array $userData): DeviceCommand
    {
        $zktecoDevice = $this->syncDevice($serialNumber);

        if (! $zktecoDevice) {
            throw new \Exception("Device not found: {$serialNumber}");
        }

        return $this->commandBuilder->addUser($zktecoDevice, $userData);
    }

    /**
     * Delete user using the ZKTeco package
     */
    public function deleteUserCommand(string $serialNumber, string $pin): DeviceCommand
    {
        $zktecoDevice = $this->syncDevice($serialNumber);

        if (! $zktecoDevice) {
            throw new \Exception("Device not found: {$serialNumber}");
        }

        return $this->commandBuilder->deleteUser($zktecoDevice, $pin);
    }

    /**
     * Clear attendance logs using the ZKTeco package
     */
    public function clearAttendanceLogs(string $serialNumber): DeviceCommand
    {
        $zktecoDevice = $this->syncDevice($serialNumber);

        if (! $zktecoDevice) {
            throw new \Exception("Device not found: {$serialNumber}");
        }

        return $this->commandBuilder->clearAttendanceLogs($zktecoDevice);
    }

    /**
     * Reboot device using the ZKTeco package
     */
    public function rebootDevice(string $serialNumber): DeviceCommand
    {
        $zktecoDevice = $this->syncDevice($serialNumber);

        if (! $zktecoDevice) {
            throw new \Exception("Device not found: {$serialNumber}");
        }

        return $this->commandBuilder->reboot($zktecoDevice);
    }

    /**
     * Sync device time using the ZKTeco package
     */
    public function syncDeviceTime(string $serialNumber): DeviceCommand
    {
        $zktecoDevice = $this->syncDevice($serialNumber);

        if (! $zktecoDevice) {
            throw new \Exception("Device not found: {$serialNumber}");
        }

        return $this->commandBuilder->syncTime($zktecoDevice);
    }

    /**
     * Get device info using the ZKTeco package
     */
    public function getDeviceInfo(string $serialNumber): DeviceCommand
    {
        $zktecoDevice = $this->syncDevice($serialNumber);

        if (! $zktecoDevice) {
            throw new \Exception("Device not found: {$serialNumber}");
        }

        return $this->commandBuilder->info($zktecoDevice);
    }

    /**
     * Migrate all pending commands from custom system to package system
     */
    public function migrateAllPendingCommands(): array
    {
        $results = [];

        // Get all devices that have pending commands
        $devicesWithPendingCommands = BiometricCommand::where('status', 'pending')
            ->distinct()
            ->pluck('device_serial_number');

        foreach ($devicesWithPendingCommands as $serialNumber) {
            $migratedCount = $this->migratePendingCommands($serialNumber);
            $results[$serialNumber] = $migratedCount;
        }

        return $results;
    }
}

#!/usr/bin/env php
<?php

/**
 * ZKTeco Command Status Checker
 * Run this script to check the status of CREATEUSER commands
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

use App\Models\BiometricCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricEmployee;

echo "=== ZKTeco Command Status Checker ===\n\n";

// Get recent CREATEUSER commands
$recentCommands = BiometricCommand::where('type', 'CREATEUSER')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

if ($recentCommands->isEmpty()) {
    echo "No CREATEUSER commands found.\n";
    exit(0);
}

echo "Recent CREATEUSER Commands:\n";
echo str_repeat('-', 80) . "\n";

foreach ($recentCommands as $command) {
    echo "Command ID: " . $command->command_id . "\n";
    echo "Device SN: " . $command->device_serial_number . "\n";
    echo "Employee ID: " . $command->employee_id . "\n";
    echo "Status: " . strtoupper($command->status) . "\n";

    if ($command->sent_at) {
        echo "Sent At: " . $command->sent_at->format('Y-m-d H:i:s') . "\n";
        echo "Time Since Sent: " . now()->diffInSeconds($command->sent_at) . " seconds\n";
    }

    if ($command->executed_at) {
        echo "Executed At: " . $command->executed_at->format('Y-m-d H:i:s') . "\n";
        echo "SUCCESS: User was created on device!\n";
    }

    if ($command->failed_at) {
        echo "Failed At: " . $command->failed_at->format('Y-m-d H:i:s') . "\n";
        echo "ERROR: Command execution failed\n";
    }

    echo str_repeat('-', 80) . "\n\n";
}

// Check specific device
echo "\n=== Device Status ===\n";
$deviceSerial = 'UFS2252100853'; // Your device from logs
$device = BiometricDevice::where('serial_number', $deviceSerial)->first();

if ($device) {
    echo "Device: " . $device->device_name . "\n";
    echo "Serial: " . $device->serial_number . "\n";
    echo "IP: " . $device->device_ip . "\n";
    echo "Status: " . strtoupper($device->status) . "\n";
    echo "Last Online: " . ($device->last_online ? $device->last_online->format('Y-m-d H:i:s') : 'Never') . "\n";

    // Count commands for this device
    $pendingCount = BiometricCommand::where('device_serial_number', $deviceSerial)
        ->where('status', 'pending')
        ->count();

    $sentCount = BiometricCommand::where('device_serial_number', $deviceSerial)
        ->where('status', 'sent')
        ->count();

    $executedCount = BiometricCommand::where('device_serial_number', $deviceSerial)
        ->where('status', 'executed')
        ->count();

    $failedCount = BiometricCommand::where('device_serial_number', $deviceSerial)
        ->where('status', 'failed')
        ->count();

    echo "\nCommand Statistics:\n";
    echo "Pending: " . $pendingCount . "\n";
    echo "Sent: " . $sentCount . "\n";
    echo "Executed: " . $executedCount . "\n";
    echo "Failed: " . $failedCount . "\n";
} else {
    echo "Device not found in database.\n";
}

echo "\n=== Employee 0909 Status ===\n";
$employee = BiometricEmployee::where('biometric_employee_id', '0909')->first();

if ($employee) {
    echo "Employee ID: " . $employee->biometric_employee_id . "\n";
    echo "Has Fingerprint: " . ($employee->has_fingerprint ? 'Yes' : 'No') . "\n";
    echo "Card Number: " . ($employee->card_number ?? 'None') . "\n";
    echo "Created At: " . $employee->created_at->format('Y-m-d H:i:s') . "\n";

    // Check if user exists on device
    echo "\nTo verify user creation on device:\n";
    echo "1. Access ZKTeco device web interface\n";
    echo "2. Go to: Users → User Management\n";
    echo "3. Search for Employee ID: 0909\n";
    echo "4. If found, user creation was successful!\n";

    echo "\nAlternative verification:\n";
    echo "1. Have employee 0909 punch in on the device\n";
    echo "2. Check dashboard: https://your-domain.com/zkteco/dashboard\n";
    echo "3. Look for attendance record with employee name\n";
} else {
    echo "Employee 0909 not found in database.\n";
}

echo "\n=== Recommendations ===\n";
echo "1. If command shows 'executed' status, user was created successfully\n";
echo "2. If command shows 'sent' status, device is still processing\n";
echo "3. If command shows 'pending' status, device hasn't retrieved command yet\n";
echo "4. Check device logs in: /var/log/zkteco/ (if accessible)\n";
echo "5. Test user by having them punch in on the device\n";

echo "\nCommand completed successfully!\n";
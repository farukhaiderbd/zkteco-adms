<?php

namespace App\Console\Commands;

use App\Models\BiometricCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricEmployee;
use Illuminate\Console\Command;

class CheckZKTecoCommandStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zkteco:check-command-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the status of ZKTeco device commands';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== ZKTeco Command Status Checker ===');
        $this->newLine();

        // Get recent CREATEUSER commands
        $recentCommands = BiometricCommand::where('type', 'CREATEUSER')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($recentCommands->isEmpty()) {
            $this->error('No CREATEUSER commands found.');
            return self::SUCCESS;
        }

        $this->info('Recent CREATEUSER Commands:');
        $this->line(str_repeat('-', 80));

        foreach ($recentCommands as $command) {
            $this->info("Command ID: {$command->command_id}");
            $this->line("Device SN: {$command->device_serial_number}");
            $this->line("Employee ID: {$command->employee_id}");
            $this->line("Status: ".strtoupper($command->status));

            if ($command->sent_at) {
                $this->line("Sent At: {$command->sent_at->format('Y-m-d H:i:s')}");
                $this->line("Time Since Sent: ".now()->diffInSeconds($command->sent_at).' seconds');
            }

            if ($command->executed_at) {
                $this->line("Executed At: {$command->executed_at->format('Y-m-d H:i:s')}");
                $this->info('✅ SUCCESS: User was created on device!');
            }

            if ($command->failed_at) {
                $this->line("Failed At: {$command->failed_at->format('Y-m-d H:i:s')}");
                $this->error('❌ ERROR: Command execution failed');
            }

            $this->line(str_repeat('-', 80));
            $this->newLine();
        }

        // Check specific device from logs
        $this->info('=== Device Status ===');
        $deviceSerial = 'UFS2252100853';
        $device = BiometricDevice::where('serial_number', $deviceSerial)->first();

        if ($device) {
            $this->line("Device: {$device->device_name}");
            $this->line("Serial: {$device->serial_number}");
            $this->line("IP: {$device->device_ip}");
            $this->line("Status: ".strtoupper($device->status));
            $this->line("Last Online: ".($device->last_online ? $device->last_online->format('Y-m-d H:i:s') : 'Never'));

            // Count commands for this device
            $pendingCount = BiometricCommand::where('device_serial_number', $deviceSerial)
                ->where('status', 'pending')->count();

            $sentCount = BiometricCommand::where('device_serial_number', $deviceSerial)
                ->where('status', 'sent')->count();

            $executedCount = BiometricCommand::where('device_serial_number', $deviceSerial)
                ->where('status', 'executed')->count();

            $failedCount = BiometricCommand::where('device_serial_number', $deviceSerial)
                ->where('status', 'failed')->count();

            $this->newLine();
            $this->info('Command Statistics:');
            $this->line("Pending: {$pendingCount}");
            $this->line("Sent: {$sentCount}");
            $this->line("Executed: {$executedCount}");
            $this->line("Failed: {$failedCount}");
        } else {
            $this->error('Device not found in database.');
        }

        // Check Employee 0909
        $this->newLine();
        $this->info('=== Employee 0909 Status ===');
        $employee = BiometricEmployee::where('biometric_employee_id', '0909')->first();

        if ($employee) {
            $this->line("Employee ID: {$employee->biometric_employee_id}");
            $this->line("Has Fingerprint: ".($employee->has_fingerprint ? 'Yes' : 'No'));
            $this->line("Card Number: ".($employee->card_number ?? 'None'));
            $this->line("Created At: {$employee->created_at->format('Y-m-d H:i:s')}");

            $this->newLine();
            $this->info('To verify user creation on device:');
            $this->line('1. Access ZKTeco device web interface');
            $this->line('2. Go to: Users → User Management');
            $this->line('3. Search for Employee ID: 0909');
            $this->line('4. If found, user creation was successful!');

            $this->newLine();
            $this->info('Alternative verification:');
            $this->line('1. Have employee 0909 punch in on the device');
            $this->line('2. Check dashboard: https://your-domain.com/zkteco/dashboard');
            $this->line('3. Look for attendance record with employee name');
        } else {
            $this->error('Employee 0909 not found in database.');
        }

        $this->newLine();
        $this->info('=== Recommendations ===');
        $this->line('1. If command shows "executed" status, user was created successfully');
        $this->line('2. If command shows "sent" status, device is still processing');
        $this->line('3. If command shows "pending" status, device hasn't retrieved command yet');
        $this->line('4. Check device logs in: /var/log/zkteco/ (if accessible)');
        $this->line('5. Test user by having them punch in on the device');

        return self::SUCCESS;
    }
}
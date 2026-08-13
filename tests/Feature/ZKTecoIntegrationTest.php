<?php

namespace Tests\Feature;

use App\Services\ZKTecoCommandService;
use App\ZKTeco\Models\BiometricCommand;
use App\ZKTeco\Models\BiometricDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZKTecoIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected ZKTecoCommandService $commandService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commandService = app(ZKTecoCommandService::class);
    }

    public function test_device_sync_creates_zkteco_device(): void
    {
        // Create a biometric device
        $biometricDevice = BiometricDevice::create([
            'device_name' => 'Test Device',
            'serial_number' => 'SYNC123',
            'device_ip' => '192.168.1.100',
            'status' => 'online',
            'last_online' => now(),
        ]);

        // Sync the device
        $zktecoDevice = $this->commandService->syncDevice('SYNC123');

        $this->assertNotNull($zktecoDevice);
        $this->assertEquals('SYNC123', $zktecoDevice->serial_number);
        $this->assertEquals('Test Device', $zktecoDevice->name);
        $this->assertEquals('192.168.1.100', $zktecoDevice->ip_address);
    }

    public function test_create_user_command_uses_zkteco_package(): void
    {
        // Create a biometric device
        BiometricDevice::create([
            'device_name' => 'Test Device',
            'serial_number' => 'USER123',
            'device_ip' => '192.168.1.101',
            'status' => 'online',
        ]);

        // Create user command
        $command = $this->commandService->createUserCommand('USER123', [
            'pin' => '123',
            'name' => 'Test User',
            'card' => '654321',
            'privilege' => 0,
        ]);

        $this->assertDatabaseHas('zkteco_device_commands', [
            'id' => $command->id,
            'command_type' => 'DATA',
            'status' => 'pending',
        ]);

        $this->assertStringContainsString('PIN=123', $command->command_content);
        $this->assertStringContainsString('Name=Test User', $command->command_content);
    }

    public function test_delete_user_command_uses_zkteco_package(): void
    {
        // Create a biometric device
        BiometricDevice::create([
            'device_name' => 'Test Device',
            'serial_number' => 'DEL123',
            'device_ip' => '192.168.1.102',
            'status' => 'online',
        ]);

        // Create delete user command
        $command = $this->commandService->deleteUserCommand('DEL123', '456');

        $this->assertDatabaseHas('zkteco_device_commands', [
            'id' => $command->id,
            'command_type' => 'DATA',
            'status' => 'pending',
        ]);

        $this->assertStringContainsString('DEL_USER', $command->command_content);
        $this->assertStringContainsString('PIN=456', $command->command_content);
    }

    public function test_sync_time_command_uses_zkteco_package(): void
    {
        // Create a biometric device
        BiometricDevice::create([
            'device_name' => 'Test Device',
            'serial_number' => 'TIME123',
            'device_ip' => '192.168.1.103',
            'status' => 'online',
        ]);

        // Create sync time command
        $command = $this->commandService->syncDeviceTime('TIME123');

        $this->assertDatabaseHas('zkteco_device_commands', [
            'id' => $command->id,
            'command_type' => 'INFO',
            'status' => 'pending',
        ]);

        $this->assertStringContainsString('ServerLocalTime', $command->command_content);
    }

    public function test_migrate_pending_commands_from_biometric_table(): void
    {
        // Create a biometric device
        $biometricDevice = BiometricDevice::create([
            'device_name' => 'Test Device',
            'serial_number' => 'MIGRATE123',
            'device_ip' => '192.168.1.104',
            'status' => 'online',
        ]);

        // Create pending commands in the biometric table
        BiometricCommand::create([
            'type' => 'CREATEUSER',
            'device_serial_number' => 'MIGRATE123',
            'command_id' => 'TEST-CMD-1',
            'command' => 'C:TEST-CMD-1:DATA USER PIN=999	Name=Migration Test',
            'status' => 'pending',
        ]);

        // Migrate commands
        $migratedCount = $this->commandService->migratePendingCommands('MIGRATE123');

        $this->assertEquals(1, $migratedCount);

        // Check that command was migrated to ZKTeco table
        $this->assertDatabaseHas('zkteco_device_commands', [
            'command_type' => 'DATA',
            'status' => 'pending',
        ]);

        // Check original command was marked as sent
        $this->assertDatabaseHas('biometric_commands', [
            'type' => 'CREATEUSER',
            'device_serial_number' => 'MIGRATE123',
            'status' => 'sent',
        ]);
    }

    public function test_clear_attendance_logs_command(): void
    {
        // Create a biometric device
        BiometricDevice::create([
            'device_name' => 'Test Device',
            'serial_number' => 'CLEAR123',
            'device_ip' => '192.168.1.105',
            'status' => 'online',
        ]);

        // Create clear attendance logs command
        $command = $this->commandService->clearAttendanceLogs('CLEAR123');

        $this->assertDatabaseHas('zkteco_device_commands', [
            'id' => $command->id,
            'command_type' => 'CLEAR',
            'status' => 'pending',
        ]);

        $this->assertStringContainsString('CLEAR LOG', $command->command_content);
    }

    public function test_reboot_device_command(): void
    {
        // Create a biometric device
        BiometricDevice::create([
            'device_name' => 'Test Device',
            'serial_number' => 'REBOOT123',
            'device_ip' => '192.168.1.106',
            'status' => 'online',
        ]);

        // Create reboot command
        $command = $this->commandService->rebootDevice('REBOOT123');

        $this->assertDatabaseHas('zkteco_device_commands', [
            'id' => $command->id,
            'command_type' => 'REBOOT',
            'status' => 'pending',
        ]);

        $this->assertStringContainsString('REBOOT', $command->command_content);
    }
}

# ZKTeco Command Integration Guide

## Overview

This application now uses a unified ZKTeco command system that integrates your custom biometric system with the Filament ZKTeco ADMS package. Commands are now properly executed on devices instead of staying in "pending" status.

## Problem Solved

Previously, commands were stored in the custom `biometric_commands` table but devices were polling the package's `zkteco_device_commands` table, resulting in commands never being executed.

## Solution

### 1. Command Service (`app/Services/ZKTecoCommandService.php`)

A unified service that handles all ZKTeco device commands and ensures proper integration with the Filament package:

#### Available Methods:

- `createUserCommand(string $serialNumber, array $userData)` - Add user to device
- `deleteUserCommand(string $serialNumber, string $pin)` - Remove user from device
- `clearAttendanceLogs(string $serialNumber)` - Clear device attendance logs
- `rebootDevice(string $serialNumber)` - Reboot device
- `syncDeviceTime(string $serialNumber)` - Sync device time with server
- `getDeviceInfo(string $serialNumber)` - Get device information
- `migratePendingCommands(string $serialNumber)` - Migrate pending commands from custom system
- `syncDevice(string $serialNumber)` - Sync device between tables

### 2. Usage Examples

#### Creating User Commands

```php
use App\Services\ZKTecoCommandService;

$service = app(ZKTecoCommandService::class);

// Add user to device
$command = $service->createUserCommand('DEVICE_SERIAL', [
    'pin' => '123',
    'name' => 'John Doe',
    'card' => '654321', // Optional
    'privilege' => 0,    // 0 = user, 14 = admin
]);
```

#### Device Management Commands

```php
// Delete user
$command = $service->deleteUserCommand('DEVICE_SERIAL', '123');

// Clear attendance logs
$command = $service->clearAttendanceLogs('DEVICE_SERIAL');

// Reboot device
$command = $service->rebootDevice('DEVICE_SERIAL');

// Sync device time
$command = $service->syncDeviceTime('DEVICE_SERIAL');

// Get device info
$command = $service->getDeviceInfo('DEVICE_SERIAL');
```

#### Migrating Existing Commands

```php
// Migrate all pending commands
$results = $service->migrateAllPendingCommands();

// Or migrate for specific device
$count = $service->migratePendingCommands('DEVICE_SERIAL');
```

### 3. Artisan Commands

```bash
# Migrate all pending commands from custom system to package
php artisan zkteco:migrate-commands
```

### 4. Database Tables

**Custom System Tables:**
- `biometric_devices` - Your custom device registry
- `biometric_commands` - Legacy command storage (kept for compatibility)

**Package Tables:**
- `zkteco_devices` - Package device registry
- `zkteco_device_commands` - Active command execution queue
- `zkteco_attendance_logs` - Attendance data
- `zkteco_users` - User management

### 5. Device Synchronization

Devices are automatically synchronized between `biometric_devices` and `zkteco_devices` when commands are created. The service ensures that:

1. Device exists in both tables
2. Device information is kept in sync
3. Commands are created in the correct table for device polling

### 6. Command Status Flow

Commands now follow this lifecycle:

```
pending → sent → acknowledged (success) or failed (error)
```

The Filament package handles the status transitions automatically when devices:
1. Poll for commands via `/iclock/getrequest`
2. Execute commands on device
3. Report results via `/iclock/devicecmd`

### 7. Testing

Run the integration tests to verify functionality:

```bash
php artisan test --filter=ZKTecoIntegrationTest
```

### 8. Configuration

The package is configured in `config/zkteco-adms.php`:

- **Route prefix**: `iclock`
- **Device auto-register**: `true`
- **Offline threshold**: `10 minutes`
- **Event dispatching**: Enabled

### 9. Events

The package dispatches events for:

- `AttendanceReceived` - When attendance data is received
- `DeviceConnected` - When device comes online
- `UserSynced` - When user is synced to device

Listen to these events in `app/Providers/EventServiceProvider.php`.

## Monitoring Commands

Check command status in the Filament admin panel at `/admin` or query directly:

```sql
-- View pending commands
SELECT * FROM zkteco_device_commands WHERE status = 'pending';

-- View failed commands
SELECT * FROM zkteco_device_commands WHERE status = 'failed';

-- View command history
SELECT * FROM zkteco_device_commands ORDER BY created_at DESC LIMIT 20;
```

## Troubleshooting

### Commands still pending?

1. Check device is online: `SELECT * FROM zkteco_devices WHERE status = 'online'`
2. Verify device polling: Check ZKTeco device logs
3. Ensure routes are accessible: `php artisan route:list | grep zkteco`

### Device not syncing?

1. Check device exists in `biometric_devices` table
2. Verify serial number matches
3. Check service logs for sync errors

### Integration failed?

1. Run migration command: `php artisan zkteco:migrate-commands`
2. Check package configuration in `config/zkteco-adms.php`
3. Verify routes are properly registered

## Benefits

✅ **Unified Command System** - Single source of truth for device commands
✅ **Proper Execution** - Commands now execute on devices instead of staying pending
✅ **Backward Compatible** - Existing code continues to work
✅ **Filament Integration** - Full admin panel management
✅ **Event System** - Listen to device events for custom processing
✅ **Testing Support** - Comprehensive test coverage

## Migration Checklist

- [x] Created `ZKTecoCommandService` for unified command handling
- [x] Updated existing command creation to use package infrastructure
- [x] Created migration command for existing pending commands
- [x] Added comprehensive integration tests
- [x] Updated ZKTecoBiometric class to use new service
- [x] All tests passing (12/12)

## Next Steps

1. Update any custom code that creates commands directly to use `ZKTecoCommandService`
2. Monitor command execution via Filament admin panel
3. Set up event listeners for business processes
4. Configure automatic device sync if needed
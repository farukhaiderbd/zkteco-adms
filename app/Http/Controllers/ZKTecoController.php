<?php

namespace App\Http\Controllers;

use App\Models\BiometricAttendance;
use App\Models\BiometricCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricEmployee;
use App\Services\ZKTecoDeviceLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ZKTecoController extends Controller
{
    /**
     * Display dashboard for ZKTeco testing
     */
    public function dashboard()
    {
        $devices = BiometricDevice::all();
        $employees = BiometricEmployee::with('user')->get();
        $recentAttendances = BiometricAttendance::with(['device', 'user'])
            ->latest('timestamp')
            ->take(20)
            ->get();
        $pendingCommands = BiometricCommand::pending()->get();

        // Get recent device logs
        $recentLogs = \DB::table('zkteco_device_logs')
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();

        // Get log statistics
        $logStats = [
            'total_logs' => \DB::table('zkteco_device_logs')->count(),
            'handshakes' => \DB::table('zkteco_device_logs')->where('log_type', 'handshake')->count(),
            'attendance_logs' => \DB::table('zkteco_device_logs')->where('log_type', 'attendance')->count(),
            'command_sent' => \DB::table('zkteco_device_logs')->where('log_type', 'command_sent')->count(),
            'errors' => \DB::table('zkteco_device_logs')->where('log_type', 'error')->count(),
        ];

        return view('zkteco.dashboard', compact(
            'devices',
            'employees',
            'recentAttendances',
            'pendingCommands',
            'recentLogs',
            'logStats'
        ));
    }

    /**
     * Store a new biometric device
     */
    public function storeDevice(Request $request)
    {
        $validated = $request->validate([
            'device_name' => 'required|string|max:255',
            'serial_number' => 'required|string|unique:biometric_devices,serial_number',
            'device_ip' => 'required|ip',
        ]);

        $device = BiometricDevice::create([
            'device_name' => $validated['device_name'],
            'serial_number' => $validated['serial_number'],
            'device_ip' => $validated['device_ip'],
            'status' => 'pending',
        ]);

        return redirect()->back()
            ->with('success', "Device '{$device->device_name}' added successfully!");
    }

    /**
     * Create a new biometric employee
     */
    public function storeEmployee(Request $request)
    {
        $validated = $request->validate([
            'biometric_employee_id' => 'required|string|unique:biometric_employees,biometric_employee_id',
            'card_number' => 'nullable|string',
            'has_fingerprint' => 'boolean',
            'has_photo' => 'boolean',
        ]);

        $employee = BiometricEmployee::create([
            'biometric_employee_id' => $validated['biometric_employee_id'],
            'card_number' => $validated['card_number'] ?? null,
            'has_fingerprint' => $validated['has_fingerprint'] ?? false,
            'has_photo' => $validated['has_photo'] ?? false,
            'force_biometric_clockin' => true,
        ]);

        return redirect()->back()
            ->with('success', "Employee '{$employee->biometric_employee_id}' created successfully!");
    }

    /**
     * Create a user command for the device
     */
    public function createUserCommand(Request $request)
    {
        $validated = $request->validate([
            'device_serial_number' => 'required|string|exists:biometric_devices,serial_number',
            'biometric_employee_id' => 'required|exists:biometric_employees,id',
        ]);

        $device = BiometricDevice::where('serial_number', $validated['device_serial_number'])->first();
        $employee = BiometricEmployee::find($validated['biometric_employee_id']);

        $commandId = 'CREATEUSER-'.Str::uuid();
        $employeeName = $employee->user ? $employee->user->name : "Employee {$employee->biometric_employee_id}";
        $command = "C:{$commandId}:DATA USER PIN={$employee->biometric_employee_id}\tName={$employeeName}\n";

        BiometricCommand::create([
            'device_serial_number' => $validated['device_serial_number'],
            'command_id' => $commandId,
            'command' => $command,
            'type' => 'CREATEUSER',
            'employee_id' => $employee->biometric_employee_id,
            'status' => 'pending',
        ]);

        return redirect()->back()
            ->with('success', "User creation command sent for '{$employeeName}'!");
    }

    /**
     * ZKTeco Device handshake endpoint (GET /iclock/cdata)
     */
    public function deviceHandshake(Request $request)
    {
        $serialNumber = $request->query('SN');
        $deviceIp = $request->ip();

        $logger = ZKTecoDeviceLogger::forRequest($serialNumber, $deviceIp)
            ->setEndpoint('/iclock/cdata');

        if (! $serialNumber) {
            $logger->logError('Missing serial number in handshake');

            return response('Missing serial number', 400);
        }

        $device = BiometricDevice::where('serial_number', $serialNumber)->first();

        if ($device) {
            $device->update([
                'status' => 'online',
                'last_online' => now(),
            ]);

            $logger->logHandshake([
                'device_name' => $device->device_name,
                'previous_status' => $device->getOriginal('status'),
                'user_agent' => $request->userAgent(),
            ]);
        } else {
            $logger->logError('Device not found', [
                'requested_serial' => $serialNumber,
            ]);
        }

        return response('OK');
    }

    /**
     * Receive attendance data from device (POST /iclock/cdata)
     */
    public function receiveAttendance(Request $request)
    {
        $data = $request->getContent();
        $serialNumber = $request->header('SN');
        $deviceIp = $request->ip();

        $logger = ZKTecoDeviceLogger::forRequest($serialNumber, $deviceIp)
            ->setEndpoint('/iclock/cdata');

        // Parse attendance data from ZKTeco format
        // Format: USER_ID\tTIMESTAMP\tVERIFICATION_TYPE\t...
        $lines = explode("\n", $data);
        $attendanceCount = 0;
        $processedEmployees = [];

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $parts = explode("\t", $line);
            if (count($parts) < 2) {
                continue;
            }

            $userId = $parts[0];
            $timestamp = $parts[1];
            $verificationType = $parts[2] ?? 0;

            $device = BiometricDevice::where('serial_number', $serialNumber)->first();

            if ($device) {
                // Update device status
                $device->update([
                    'status' => 'online',
                    'last_online' => now(),
                ]);

                // Create attendance record with correct column names
                \DB::table('biometric_device_attendances')->insert([
                    'device_name' => $device->device_name,
                    'device_serial_number' => $device->serial_number,
                    'employee_id' => $userId,
                    'timestamp' => $timestamp,
                    'status1' => $verificationType,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $attendanceCount++;
                $processedEmployees[] = $userId;
            }
        }

        $logger->logAttendanceData($attendanceCount, [
            'employees_processed' => $processedEmployees,
            'data_size' => strlen($data),
            'line_count' => count($lines),
        ]);

        return response('OK');
    }

    /**
     * Device polls for commands (GET /iclock/getrequest)
     */
    public function getCommands(Request $request)
    {
        $serialNumber = $request->query('SN');
        $deviceIp = $request->ip();

        $logger = ZKTecoDeviceLogger::forRequest($serialNumber, $deviceIp)
            ->setEndpoint('/iclock/getrequest');

        if (! $serialNumber) {
            $logger->logError('Missing serial number in getrequest');

            return response('');
        }

        $commands = BiometricCommand::where('device_serial_number', $serialNumber)
            ->where('status', 'pending')
            ->limit(5)
            ->get();

        $response = '';
        $sentCommands = [];

        foreach ($commands as $command) {
            $response .= $command->command;
            $command->update(['status' => 'sent', 'sent_at' => now()]);
            $sentCommands[] = [
                'command_id' => $command->command_id,
                'type' => $command->type,
                'employee_id' => $command->employee_id,
            ];

            $logger->logCommandSent($command->command_id, $command->type, [
                'employee_id' => $command->employee_id,
                'command' => substr($command->command, 0, 100).'...',
            ]);
        }

        $logger->log('info', "Command polling completed - {$commands->count()} commands sent", [
            'commands_sent' => count($sentCommands),
            'response_size' => strlen($response),
        ]);

        return response($response);
    }

    /**
     * Receive command execution results (POST /iclock/devicecmd)
     */
    public function receiveCommandResult(Request $request)
    {
        $data = $request->getContent();
        $serialNumber = $request->get('SN');
        $deviceIp = $request->ip();

        $logger = ZKTecoDeviceLogger::forRequest($serialNumber, $deviceIp)
            ->setEndpoint('/iclock/devicecmd');

        // ZKTeco devices send results in format: CMD=XXX&ID=XXX&Return=XXX
        parse_str($data, $parsed);

        $commandId = $parsed['ID'] ?? '';
        $returnCode = $parsed['Return'] ?? '';
        $command = $parsed['CMD'] ?? '';

        if ($commandId) {
            $dbCommand = BiometricCommand::where('command_id', $commandId)->first();

            if ($dbCommand) {
                if ($returnCode === '0' || $returnCode === 'OK') {
                    $dbCommand->update([
                        'status' => 'executed',
                        'executed_at' => now(),
                    ]);

                    $logger->logCommandResult($commandId, 'SUCCESS', [
                        'command_type' => $dbCommand->type,
                        'employee_id' => $dbCommand->employee_id,
                    ]);
                } else {
                    $dbCommand->update([
                        'status' => 'failed',
                        'failed_at' => now(),
                    ]);

                    $logger->logCommandResult($commandId, 'FAILED', [
                        'command_type' => $dbCommand->type,
                        'error_code' => $returnCode,
                    ]);
                }
            }
        }

        $logger->log('info', 'Command results processed', [
            'command_id' => $commandId,
            'return_code' => $returnCode,
            'command' => $command,
        ]);

        return response('OK');
    }

    /**
     * Test device connection
     */
    public function testConnection(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|exists:biometric_devices,id',
        ]);

        $device = BiometricDevice::find($validated['device_id']);

        // Simple connection test using fsockopen (ZKTeco default port 4370)
        $connection = @fsockopen($device->device_ip, 4370, $errno, $errstr, 5);

        if ($connection) {
            fclose($connection);
            $device->markAsOnline();

            return redirect()->back()->with('success', 'Device is reachable!');
        } else {
            $device->update(['status' => 'offline']);

            return redirect()->back()->with('error', "Cannot connect to device: {$errstr}");
        }
    }

    /**
     * Check command execution status
     */
    public function checkCommandStatus($commandId)
    {
        $command = BiometricCommand::where('command_id', $commandId)->first();

        if (! $command) {
            return response()->json(['error' => 'Command not found'], 404);
        }

        return response()->json([
            'command_id' => $command->command_id,
            'type' => $command->type,
            'status' => $command->status,
            'employee_id' => $command->employee_id,
            'sent_at' => $command->sent_at,
            'executed_at' => $command->executed_at,
            'failed_at' => $command->failed_at,
            'created_at' => $command->created_at,
            'time_since_sent' => $command->sent_at ? now()->diffInSeconds($command->sent_at) : null,
        ]);
    }

    /**
     * Get all pending commands for a device
     */
    public function getPendingCommands($deviceSerial)
    {
        $commands = BiometricCommand::where('device_serial_number', $deviceSerial)
            ->where('status', 'pending')
            ->get();

        return response()->json([
            'device_serial' => $deviceSerial,
            'pending_count' => $commands->count(),
            'commands' => $commands->map(function ($command) {
                return [
                    'command_id' => $command->command_id,
                    'type' => $command->type,
                    'employee_id' => $command->employee_id,
                    'created_at' => $command->created_at,
                ];
            }),
        ]);
    }

    /**
     * Get recent commands for a device with their status
     */
    public function getDeviceCommandHistory($deviceSerial, $limit = 10)
    {
        $commands = BiometricCommand::where('device_serial_number', $deviceSerial)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'device_serial' => $deviceSerial,
            'total_commands' => $commands->count(),
            'commands' => $commands->map(function ($command) {
                return [
                    'command_id' => $command->command_id,
                    'type' => $command->type,
                    'status' => $command->status,
                    'employee_id' => $command->employee_id,
                    'created_at' => $command->created_at,
                    'sent_at' => $command->sent_at,
                    'executed_at' => $command->executed_at,
                    'failed_at' => $command->failed_at,
                ];
            }),
        ]);
    }
}

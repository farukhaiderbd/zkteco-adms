<?php

namespace App\Http\Controllers;

use App\Models\BiometricAttendance;
use App\Models\BiometricCommand;
use App\Models\BiometricDevice;
use App\Models\BiometricEmployee;
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

        return view('zkteco.dashboard', compact(
            'devices',
            'employees',
            'recentAttendances',
            'pendingCommands'
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

        if (! $serialNumber) {
            return response('Missing serial number', 400);
        }

        $device = BiometricDevice::where('serial_number', $serialNumber)->first();

        if ($device) {
            $device->update([
                'status' => 'online',
                'last_online' => now(),
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

        // Parse attendance data from ZKTeco format
        // Format: USER_ID\tTIMESTAMP\tVERIFICATION_TYPE\t...
        $lines = explode("\n", $data);

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

            $device = BiometricDevice::where('serial_number', $request->header('SN'))->first();

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
            }
        }

        return response('OK');
    }

    /**
     * Device polls for commands (GET /iclock/getrequest)
     */
    public function getCommands(Request $request)
    {
        $serialNumber = $request->query('SN');

        if (! $serialNumber) {
            return response('');
        }

        $commands = BiometricCommand::where('device_serial_number', $serialNumber)
            ->where('status', 'pending')
            ->limit(5)
            ->get();

        $response = '';

        foreach ($commands as $command) {
            $response .= $command->command;
            $command->markAsSent();
        }

        return response($response);
    }

    /**
     * Receive command execution results (POST /iclock/devicecmd)
     */
    public function receiveCommandResult(Request $request)
    {
        $data = $request->getContent();

        // Parse command result format
        // Format: COMMAND_ID\tRESULT\t...
        $lines = explode("\n", $data);

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $parts = explode("\t", $line);
            $commandId = $parts[0] ?? '';
            $result = $parts[1] ?? 'OK';

            $command = BiometricCommand::where('command_id', $commandId)->first();

            if ($command) {
                if ($result === 'OK') {
                    $command->markAsCompleted($result);

                    // Update employee sync status
                    if ($command->biometricEmployee && $command->command_type === 'CREATEUSER') {
                        $command->biometricEmployee->update([
                            'synced_to_device' => true,
                            'last_sync' => now(),
                        ]);
                    }
                } else {
                    $command->markAsFailed($result);
                }
            }
        }

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
}

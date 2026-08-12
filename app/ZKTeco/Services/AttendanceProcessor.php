<?php

namespace App\ZKTeco\Services;

use App\ZKTeco\Models\BiometricDevice;
use App\ZKTeco\Models\BiometricEmployee;
use App\ZKTeco\Traits\HasLogging;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceProcessor
{
    use HasLogging;

    /**
     * Process attendance rows from device data.
     */
    public function processAttendanceRows(array $rows, BiometricDevice $device, Request $request): void
    {
        $this->logInfo('Processing attendance rows', [
            'device_serial' => $device->serial_number,
            'rows_count' => count($rows),
        ]);

        foreach ($rows as $line) {
            if (empty($line)) {
                continue;
            }

            $parts = explode("\t", $line);

            if (count($parts) >= 2) {
                $this->processAttendanceRecord($parts, $device, $request);
            }
        }
    }

    /**
     * Process a single attendance record.
     */
    protected function processAttendanceRecord(array $parts, BiometricDevice $device, Request $request): void
    {
        $deviceEmployeeId = $parts[0];
        $timestamp = $parts[1];
        $status = $parts[2] ?? 0;

        // Skip if timestamp is invalid
        if ($timestamp == 0 || ! strtotime($timestamp)) {
            return;
        }

        // Convert timezone
        $timestamp = Carbon::parse((string) $timestamp, config('zkteco-biometric.timezone', 'UTC'))
            ->format('Y-m-d H:i:s');

        // Check if record already exists
        if ($this->recordExists($deviceEmployeeId, $timestamp, $device->serial_number)) {
            return;
        }

        // Find or create biometric employee
        $biometricEmployee = $this->findOrCreateBiometricEmployee($deviceEmployeeId, $device);

        // Determine attendance status (clock in/out)
        $status = $this->determineAttendanceStatus($deviceEmployeeId, $timestamp, $device);

        // Create attendance record
        $this->createAttendanceRecord($parts, $device, $request, $biometricEmployee, $status, $timestamp);

        // Process application attendance if enabled
        if (config('zkteco-biometric.attendance.log_attendance', true) && $biometricEmployee && $biometricEmployee->user) {
            $this->processApplicationAttendance($biometricEmployee->user, $timestamp);
        }
    }

    /**
     * Check if attendance record already exists.
     */
    protected function recordExists(string $employeeId, string $timestamp, string $deviceSerial): bool
    {
        return DB::table(config('zkteco-biometric.database.tables.attendances', 'biometric_device_attendances'))
            ->where('employee_id', $employeeId)
            ->where('timestamp', $timestamp)
            ->where('device_serial_number', $deviceSerial)
            ->exists();
    }

    /**
     * Find or create biometric employee.
     */
    protected function findOrCreateBiometricEmployee(string $employeeId, BiometricDevice $device): ?BiometricEmployee
    {
        $biometricEmployee = BiometricEmployee::where('biometric_employee_id', $employeeId)->first();

        if (! $biometricEmployee && config('zkteco-biometric.attendance.auto_create_users', true)) {
            // Try to find user by employee ID if you have a mapping
            $userId = $this->findUserIdByEmployeeId($employeeId);

            if ($userId) {
                $biometricEmployee = BiometricEmployee::create([
                    'biometric_employee_id' => $employeeId,
                    'user_id' => $userId,
                ]);
            }
        }

        return $biometricEmployee;
    }

    /**
     * Find user ID by employee ID (override this method for custom mapping).
     */
    protected function findUserIdByEmployeeId(string $employeeId): ?int
    {
        // This is a basic implementation - override in your application
        // You might want to look up in an employee_details table or similar

        // Example: Check if there's a user with employee_id matching
        $userModel = config('auth.providers.users.model', 'App\Models\User');

        if (class_exists($userModel)) {
            $user = $userModel::where('employee_id', $employeeId)->first();

            return $user?->id;
        }

        return null;
    }

    /**
     * Determine attendance status based on last record.
     */
    protected function determineAttendanceStatus(string $employeeId, string $timestamp, BiometricDevice $device): int
    {
        $timestampDate = date('Y-m-d', strtotime($timestamp));

        $lastRecord = DB::table(config('zkteco-biometric.database.tables.attendances', 'biometric_device_attendances'))
            ->where('employee_id', $employeeId)
            ->whereDate('timestamp', $timestampDate)
            ->orderBy('timestamp', 'desc')
            ->first();

        // Default to clock in (0) if no record exists
        if (! $lastRecord) {
            return 0;
        }

        // Toggle between clock in (0) and clock out (1)
        return $lastRecord->status1 == 0 ? 1 : 0;
    }

    /**
     * Create attendance record in database.
     */
    protected function createAttendanceRecord(
        array $parts,
        BiometricDevice $device,
        Request $request,
        ?BiometricEmployee $biometricEmployee,
        int $status,
        string $timestamp
    ): void {
        $attendanceData = [
            'device_name' => $device->device_name,
            'device_serial_number' => $device->serial_number,
            'user_id' => $biometricEmployee?->user_id,
            'table' => $request->input('table', ''),
            'stamp' => $request->input('Stamp', ''),
            'employee_id' => $parts[0],
            'timestamp' => $timestamp,
            'status1' => $status,
            'status2' => $this->validateAndFormatInteger($parts[3] ?? null),
            'status3' => $this->validateAndFormatInteger($parts[4] ?? null),
            'status4' => $this->validateAndFormatInteger($parts[5] ?? null),
            'status5' => $this->validateAndFormatInteger($parts[6] ?? null),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table(config('zkteco-biometric.database.tables.attendances', 'biometric_attendances'))
            ->insert($attendanceData);
    }

    /**
     * Process application attendance (override this method for custom attendance logic).
     */
    protected function processApplicationAttendance($user, string $timestamp): void
    {
        // This is a basic implementation - override in your application
        // for custom attendance processing logic

        if (! $user || ! method_exists($user, 'attendance')) {
            return;
        }

        $clockIn = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp);
        $carbonDate = $clockIn->copy()->startOfDay();

        // Get the last attendance record for this user on this day
        $lastAttendance = $user->attendance()
            ->whereDate('clock_in_time', $carbonDate)
            ->orderBy('clock_in_time', 'desc')
            ->first();

        // If no record exists or last record has clock_out_time, create a new clock in
        if (! $lastAttendance || $lastAttendance->clock_out_time !== null) {
            // Clock In
            $user->attendance()->create([
                'clock_in_time' => $clockIn,
                'half_day' => 'no',
                'clock_in_type' => 'biometric',
                'work_from_type' => 'office',
                'clock_in_ip' => request()->ip(),
            ]);
        } else {
            // Clock Out - if last record exists and has no clock_out_time
            $lastAttendance->update([
                'clock_out_time' => $clockIn,
                'clock_out_type' => 'biometric',
                'work_from_type' => 'office',
                'clock_out_ip' => request()->ip(),
            ]);
        }
    }

    /**
     * Validate and format integer values.
     */
    protected function validateAndFormatInteger($value): ?int
    {
        if (isset($value) && $value !== '') {
            return is_numeric($value) ? (int) $value : null;
        }

        return null;
    }
}

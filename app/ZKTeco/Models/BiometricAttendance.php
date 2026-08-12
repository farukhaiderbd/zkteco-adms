<?php

namespace App\ZKTeco\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricAttendance extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'device_name',
        'device_serial_number',
        'user_id',
        'table',
        'stamp',
        'employee_id',
        'timestamp',
        'status1',
        'status2',
        'status3',
        'status4',
        'status5',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'timestamp' => 'datetime',
        'status1' => 'integer',
        'status2' => 'integer',
        'status3' => 'integer',
        'status4' => 'integer',
        'status5' => 'integer',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('zkteco-biometric.database.tables.attendances', 'biometric_device_attendances');
    }

    /**
     * Get the user associated with this attendance record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\Models\User'), 'user_id');
    }

    /**
     * Get the device associated with this attendance record.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'device_serial_number', 'serial_number');
    }

    /**
     * Get the biometric employee associated with this attendance record.
     */
    public function biometricEmployee(): BelongsTo
    {
        return $this->belongsTo(BiometricEmployee::class, 'employee_id', 'biometric_employee_id');
    }

    /**
     * Scope for clock in records.
     */
    public function scopeClockIn($query)
    {
        return $query->where('status1', 0);
    }

    /**
     * Scope for clock out records.
     */
    public function scopeClockOut($query)
    {
        return $query->where('status1', 1);
    }

    /**
     * Check if this is a clock in record.
     */
    public function isClockIn(): bool
    {
        return $this->status1 === 0;
    }

    /**
     * Check if this is a clock out record.
     */
    public function isClockOut(): bool
    {
        return $this->status1 === 1;
    }

    /**
     * Get the attendance type as a string.
     */
    public function getAttendanceTypeAttribute(): string
    {
        return $this->isClockIn() ? 'clock_in' : 'clock_out';
    }
}

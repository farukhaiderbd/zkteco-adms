<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricAttendance extends Model
{
    protected $table = 'biometric_device_attendances';

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

    protected $casts = [
        'timestamp' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'device_serial_number', 'serial_number');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeProcessed($query)
    {
        return $query->where('status1', '>', 0);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('timestamp', $date);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('timestamp', today());
    }

    // Helper method to get employee name if you have employee data
    public function getEmployeeNameAttribute()
    {
        return $this->employee_id;
    }

    // Helper method to determine if it's clock in or clock out
    public function getAttendanceTypeAttribute()
    {
        return $this->status1 == 0 ? 'clock_in' : 'clock_out';
    }
}

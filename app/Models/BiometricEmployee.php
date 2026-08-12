<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiometricEmployee extends Model
{
    protected $fillable = [
        'biometric_employee_id',
        'user_id',
        'card_number',
        'has_fingerprint',
        'fingerprint_id',
        'fingerprint_template',
        'has_photo',
        'photo',
        'clock_in_method',
        'force_biometric_clockin',
    ];

    protected $casts = [
        'has_fingerprint' => 'boolean',
        'has_photo' => 'boolean',
        'force_biometric_clockin' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(BiometricAttendance::class, 'employee_id', 'biometric_employee_id');
    }

    public function commands(): HasMany
    {
        return $this->hasMany(BiometricCommand::class, 'employee_id', 'biometric_employee_id');
    }

    // Helper method to get employee name from related user or use ID
    public function getDisplayNameAttribute()
    {
        return $this->user ? $this->user->name : "Employee {$this->biometric_employee_id}";
    }

    public function scopeWithFingerprint($query)
    {
        return $query->where('has_fingerprint', true);
    }

    public function scopeWithPhoto($query)
    {
        return $query->where('has_photo', true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiometricDevice extends Model
{
    protected $fillable = [
        'device_name',
        'serial_number',
        'device_ip',
        'status',
        'last_online',
    ];

    protected $casts = [
        'last_online' => 'datetime',
    ];

    public function attendances(): HasMany
    {
        return $this->hasMany(BiometricAttendance::class, 'device_serial_number', 'serial_number');
    }

    public function commands(): HasMany
    {
        return $this->hasMany(BiometricCommand::class, 'device_serial_number', 'serial_number');
    }

    public function isOnline(): bool
    {
        return $this->status === 'online' &&
               $this->last_online &&
               $this->last_online->gt(now()->subMinutes(5));
    }

    public function markAsOnline(): void
    {
        $this->update([
            'status' => 'online',
            'last_online' => now(),
        ]);
    }

    public function markAsOffline(): void
    {
        $this->update(['status' => 'offline']);
    }
}

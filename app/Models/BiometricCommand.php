<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricCommand extends Model
{
    protected $fillable = [
        'device_serial_number',
        'command_id',
        'command',
        'type',
        'employee_id',
        'user_id',
        'status',
        'sent_at',
        'executed_at',
        'failed_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'executed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function biometricEmployee(): BelongsTo
    {
        return $this->belongsTo(BiometricEmployee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'executed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsCompleted(?string $response = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'response' => $response,
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'response' => $error,
            'retry_count' => $this->retry_count + 1,
        ]);
    }
}

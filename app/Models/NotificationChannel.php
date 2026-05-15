<?php

// app/Models/NotificationChannel.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'monitor_id',
        'type',
        'value',
        'notify_on_down',
        'notify_on_recovery',
        'notify_on_degraded',
    ];

    protected function casts(): array
    {
        return [
            'notify_on_down'      => 'boolean',
            'notify_on_recovery'  => 'boolean',
            'notify_on_degraded'  => 'boolean',
        ];
    }

    // ─────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    public function isEmail(): bool
    {
        return $this->type === 'email';
    }

    public function isWebhook(): bool
    {
        return $this->type === 'webhook';
    }
}
<?php

// app/Models/Monitor.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Monitor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'url',
        'check_interval',
        'threshold',
        'response_time_threshold_ms',
        'http_method',
        'follow_redirects',
        'custom_headers',
        'ssl_check_enabled',
        'ssl_valid',
        'ssl_expires_at',
        'ssl_days_remaining',
        'ssl_alert_days_before',
        'ssl_alert_sent',
        'status',
        'is_checking',
        'consecutive_failures',
        'last_checked_at',
        'next_check_at',
        'last_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'follow_redirects'           => 'boolean',
            'is_checking'                => 'boolean',
            'custom_headers'             => 'array',
            'ssl_check_enabled'          => 'boolean',
            'ssl_valid'                  => 'boolean',
            'ssl_expires_at'             => 'datetime',
            'ssl_alert_sent'             => 'boolean',
            'last_checked_at'            => 'datetime',
            'next_check_at'              => 'datetime',
            'last_notified_at'           => 'datetime',
            'consecutive_failures'       => 'integer',
            'check_interval'             => 'integer',
            'threshold'                  => 'integer',
            'response_time_threshold_ms' => 'integer',
            'ssl_days_remaining'         => 'integer',
            'ssl_alert_days_before'      => 'integer',
        ];
    }

    // ─────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(MonitorCheck::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function notificationChannels(): HasMany
    {
        return $this->hasMany(NotificationChannel::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
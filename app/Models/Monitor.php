<?php

// app/Models/Monitor.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
            'last_checked_at'            => 'datetime',
            'next_check_at'              => 'datetime',
            'last_notified_at'           => 'datetime',
            'consecutive_failures'       => 'integer',
            'check_interval'             => 'integer',
            'threshold'                  => 'integer',
            'response_time_threshold_ms' => 'integer',
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
}
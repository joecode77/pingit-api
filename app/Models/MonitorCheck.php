<?php

// app/Models/MonitorCheck.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitorCheck extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'monitor_id',
        'status_code',
        'response_time_ms',
        'is_up',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_up'            => 'boolean',
            'status_code'      => 'integer',
            'response_time_ms' => 'integer',
            'checked_at'       => 'datetime',
        ];
    }

    // ─────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
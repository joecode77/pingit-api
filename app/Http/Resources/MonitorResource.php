<?php

// app/Http/Resources/MonitorResource.php

namespace App\Http\Resources;

use App\Services\CheckService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $checkService = app(CheckService::class);

        return [
            'id'                         => $this->id,
            'url'                        => $this->url,
            'name'                       => $this->name,
            'check_interval'             => $this->check_interval,
            'threshold'                  => $this->threshold,
            'response_time_threshold_ms' => $this->response_time_threshold_ms,
            'http_method'                => $this->http_method,
            'follow_redirects'           => $this->follow_redirects,
            'custom_headers'             => $this->custom_headers,
            'ssl_check_enabled'          => $this->ssl_check_enabled,
            'ssl_valid'                  => $this->ssl_valid,
            'ssl_expires_at'             => $this->ssl_expires_at?->toIso8601String(),
            'ssl_days_remaining'         => $this->ssl_days_remaining,
            'ssl_alert_days_before'      => $this->ssl_alert_days_before,
            'status'                     => $this->status,
            'last_checked_at'            => $this->last_checked_at?->toIso8601String(),
            'uptime_percentage'          => $checkService->uptimePercentage($this->resource),
            'created_at'                 => $this->created_at->toIso8601String(),
        ];
    }
}
<?php

// app/Http/Resources/MonitorCheckResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitorCheckResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'monitor_id'         => $this->monitor_id,
            'status_code'        => $this->status_code,
            'response_time_ms'   => $this->response_time_ms,
            'dns_resolution_ms'  => $this->dns_resolution_ms,
            'is_up'              => $this->is_up,
            'checked_at'         => $this->checked_at->toIso8601String(),
        ];
    }
}
<?php

// app/Http/Resources/IncidentResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'monitor_id'       => $this->monitor_id,
            'started_at'       => $this->started_at->toIso8601String(),
            'ended_at'         => $this->ended_at?->toIso8601String(),
            'duration_seconds' => $this->duration_seconds,
            'is_ongoing'       => $this->isOngoing(),
        ];
    }
}
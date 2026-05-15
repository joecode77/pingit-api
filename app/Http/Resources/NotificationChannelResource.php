<?php

// app/Http/Resources/NotificationChannelResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationChannelResource extends JsonResource
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
            'type'               => $this->type,
            'value'              => $this->value,
            'notify_on_down'     => $this->notify_on_down,
            'notify_on_recovery' => $this->notify_on_recovery,
            'notify_on_degraded' => $this->notify_on_degraded,
            'created_at'         => $this->created_at->toIso8601String(),
        ];
    }
}
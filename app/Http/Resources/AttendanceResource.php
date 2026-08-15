<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'qr_configuration_id' => $this->qr_configuration_id,
            'user_id' => $this->user_id,
            'scanned_at' => $this->scanned_at,
            'synced_at' => $this->synced_at,
            'event' => EventResource::make($this->whenLoaded('event')),
            'user' => UserResource::make($this->whenLoaded('user')),
            'created_at' => $this->created_at,
        ];
    }
}

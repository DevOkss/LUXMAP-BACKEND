<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'organization_id' => $this->organization_id,
            'title' => $this->title,
            'description' => $this->description,
            'venue' => $this->venue,
            'time_from' => $this->time_from,
            'time_to' => $this->time_to,
            'event_date' => $this->event_date,
            'status' => $this->status,
            'organization' => OrganizationResource::make($this->whenLoaded('organization')),
            'attendees_count' => $this->when($this->attendances_count !== null, $this->attendances_count),
            'required_years' => $this->requiredYears(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function requiredYears(): array
    {
        $years = [];

        foreach ($this->qrConfigurations as $configuration) {
            $list = $configuration->required_years ?? [];

            if (in_array('all', $list, true)) {
                return ['all'];
            }

            foreach ($list as $year) {
                $year = (string) $year;
                if (!in_array($year, $years, true)) {
                    $years[] = $year;
                }
            }
        }

        return $years;
    }
}

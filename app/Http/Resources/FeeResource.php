<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'description' => $this->description,
            'amount' => (float) $this->amount,
            'term' => $this->term,
            'required_years' => $this->required_years ?? ['all'],
            'due_date' => $this->due_date,
            'status' => $this->status,
            'organization' => $this->when($this->relationLoaded('organization') && $this->organization, fn () => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
                'type' => $this->organization->type,
            ]),
            'obligation_status' => $this->obligation_status ?? null,
        ];
    }
}

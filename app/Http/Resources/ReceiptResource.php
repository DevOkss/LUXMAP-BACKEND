<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'receipt_number' => $this->receipt_number,
            'issued_at' => $this->issued_at,
            'notes' => $this->notes,
            'payment' => $this->when($this->relationLoaded('payment') && $this->payment, fn() => [
                'id' => $this->payment->id,
                'amount' => (float) $this->payment->amount,
                'payment_method' => $this->payment->payment_method,
                'status' => $this->payment->status,
                'paid_at' => $this->payment->paid_at,
                'user' => $this->payment->user ? [
                    'id' => $this->payment->user->id,
                    'name' => $this->payment->user->name,
                    'student_number' => $this->payment->user->student_number,
                ] : null,
                'organization' => $this->payment->organization ? [
                    'id' => $this->payment->organization->id,
                    'name' => $this->payment->organization->name,
                ] : null,
            ]),
            'issued_by' => $this->when($this->relationLoaded('issuedBy') && $this->issuedBy, fn() => [
                'id' => $this->issuedBy->id,
                'name' => $this->issuedBy->name,
            ]),
        ];
    }
}

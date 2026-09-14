<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Resolve batch payments for the receipt (one receipt per batch -> many payments)
        $batchId = $this->batch_id ?? $this->payment?->batch_id;
        $batchPayments = null;
        $total = null;
        $items = null;
        if ($batchId) {
            $batchPayments = \App\Models\Payment::with(['fee', 'event', 'event.organization'])
                ->where('batch_id', $batchId)
                ->get();
            $total = (float) $batchPayments->sum('amount');
            $items = $batchPayments->map(fn (\App\Models\Payment $p) => [
                'id' => $p->id,
                'fee_type' => $p->fee_type,
                'amount' => (float) $p->amount,
                'status' => $p->status,
                'isExempted' => (bool) $p->isExempted,
                'fee' => $p->fee ? ['id' => $p->fee->id, 'name' => $p->fee->name] : null,
                'event' => $p->event ? ['id' => $p->event->id, 'title' => $p->event->title, 'event_date' => $p->event->event_date] : null,
            ])->values();
        }

        return [
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'batch_id' => $batchId,
            'receipt_number' => $this->receipt_number,
            'issued_at' => $this->issued_at,
            'notes' => $this->notes,
            'total' => $total ?? ($this->payment ? (float) $this->payment->amount : null),
            'items' => $items,
            'payments' => $batchPayments ? $batchPayments->map(fn (\App\Models\Payment $p) => [
                'id' => $p->id,
                'uuid' => $p->uuid,
                'fee_type' => $p->fee_type,
                'amount' => (float) $p->amount,
                'fee' => $p->fee ? ['id' => $p->fee->id, 'name' => $p->fee->name] : null,
                'event' => $p->event ? ['id' => $p->event->id, 'title' => $p->event->title] : null,
            ])->values() : null,
            'payment' => $this->when($this->relationLoaded('payment') && $this->payment, fn() => [
                'id' => $this->payment->id,
                'batch_id' => $this->payment->batch_id,
                'amount' => (float) $this->payment->amount,
                'payment_method' => $this->payment->payment_method,
                'status' => $this->payment->status,
                'paid_at' => $this->payment->paid_at,
                'isExempted' => (bool) $this->payment->isExempted,
                'user' => $this->payment->user ? [
                    'id' => $this->payment->user->id,
                    'name' => $this->payment->user->name,
                    'student_number' => $this->payment->user->student_number,
                ] : null,
                'organization' => $this->payment->organization ? [
                    'id' => $this->payment->organization->id,
                    'name' => $this->payment->organization->name,
                ] : null,
                'processedBy' => $this->payment->relationLoaded('processedBy') && $this->payment->processedBy
                    ? ['id' => $this->payment->processedBy->id, 'name' => $this->payment->processedBy->name]
                    : null,
                'exemptedBy' => $this->payment->relationLoaded('exemptedBy') && $this->payment->exemptedBy
                    ? ['id' => $this->payment->exemptedBy->id, 'name' => $this->payment->exemptedBy->name]
                    : null,
                'verifiedBy' => $this->payment->relationLoaded('submission') && $this->payment->submission && $this->payment->submission->relationLoaded('verifiedBy') && $this->payment->submission->verifiedBy
                    ? ['id' => $this->payment->submission->verifiedBy->id, 'name' => $this->payment->submission->verifiedBy->name]
                    : null,
            ]),
            'issued_by' => $this->when($this->relationLoaded('issuedBy') && $this->issuedBy, fn() => [
                'id' => $this->issuedBy->id,
                'name' => $this->issuedBy->name,
            ]),
        ];
    }
}

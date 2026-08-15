<?php

namespace App\Http\Resources;

use App\Models\Attendance;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Scanned QR configuration ids for this payment's user on this event.
     */
    private function scannedQrIds(): \Illuminate\Support\Collection
    {
        $event = $this->event;
        if (! $event) {
            return collect();
        }

        return Attendance::where('user_id', $this->user_id)
            ->whereIn('qr_configuration_id', $event->qrConfigurations->pluck('id'))
            ->pluck('qr_configuration_id')
            ->map(fn ($id) => (int) $id)
            ->unique();
    }

    /**
     * QR configurations of this event the user did not scan. Derived from
     * attendance; no persisted column.
     */
    private function missingQrConfigurations(): \Illuminate\Support\Collection
    {
        $event = $this->event;
        if (! $event) {
            return collect();
        }

        if (! $event->relationLoaded('qrConfigurations')) {
            $event->load('qrConfigurations');
        }

        $scanned = $this->scannedQrIds();

        return $event->qrConfigurations
            ->reject(fn ($qr) => $scanned->contains((int) $qr->id))
            ->values();
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'organization_id' => $this->organization_id,
            'academic_term_id' => $this->academic_term_id,
            'academic_term' => $this->when($this->relationLoaded('academicTerm') && $this->academicTerm, fn() => $this->academicTerm->displayName()),
            'fee_type' => $this->fee_type,
            'fee_id' => $this->fee_id,
            'event_id' => $this->event_id,
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method,
            'reference_number' => $this->reference_number,
            'status' => $this->status,
            'isExempted' => (bool) $this->isExempted,
            'exempted_at' => $this->exempted_at,
            'paid_at' => $this->paid_at,
            'notes' => $this->notes,
            'user' => $this->when($this->relationLoaded('user') && $this->user, fn() => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'student_number' => $this->user->student_number,
            ]),
            'organization' => $this->when($this->relationLoaded('organization') && $this->organization, fn() => [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
            ]),
            'fee' => $this->when($this->relationLoaded('fee') && $this->fee, fn() => [
                'id' => $this->fee->id,
                'name' => $this->fee->name,
            ]),
            'event' => $this->when($this->relationLoaded('event') && $this->event, fn() => [
                'id' => $this->event->id,
                'title' => $this->event->title,
                'event_date' => $this->event->event_date,
                'organization' => $this->event->relationLoaded('organization') && $this->event->organization
                    ? [
                        'id' => $this->event->organization->id,
                        'name' => $this->event->organization->name,
                        'type' => $this->event->organization->type,
                    ]
                    : null,
            ]),
            'absences' => $this->when(
                $this->fee_type === Payment::TYPE_PENALTY
                    && $this->relationLoaded('event')
                    && $this->event,
                fn() => $this->missingQrConfigurations()->count()
            ),
            'missing_qr_configurations' => $this->when(
                $this->fee_type === Payment::TYPE_PENALTY
                    && $this->relationLoaded('event')
                    && $this->event,
                fn() => $this->missingQrConfigurations()->map(fn ($qr) => [
                    'id' => $qr->id,
                    'type' => $qr->type,
                    'valid_from' => $qr->valid_from,
                    'valid_until' => $qr->valid_until,
                ])->values()
            ),
            'receipt' => $this->when($this->relationLoaded('receipt') && $this->receipt, fn() => [
                'id' => $this->receipt->id,
                'receipt_number' => $this->receipt->receipt_number,
                'issued_at' => $this->receipt->issued_at,
            ]),
        ];
    }
}

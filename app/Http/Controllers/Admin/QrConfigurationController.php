<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\QrConfiguration;
use App\Services\AccessScopeService;
use App\Services\EventService;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class QrConfigurationController extends Controller
{
    public function __construct(
        private AccessScopeService $accessScopeService,
        private QrCodeService $qrCodeService,
        private EventService $eventService,
    ) {}

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeEvent($request->user(), $event);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:time_in,time_out'],
            'valid_from' => ['required', 'date_format:H:i'],
            'valid_until' => ['required', 'date_format:H:i', 'after:valid_from'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geofence_radius' => ['nullable', 'integer', 'min:1'],
            'required_years' => ['nullable', 'array'],
            'required_years.*' => ['string', 'in:1,2,3,4,all'],
            'reuse_from' => ['nullable', 'integer', 'exists:qr_configurations,id'],
        ]);

        if ($request->input('reuse_from')) {
            $previous = QrConfiguration::findOrFail($request->input('reuse_from'));
            abort_unless($previous->event_id === $event->id, 422, 'Can only reuse a configuration from this event.');
            $validated['latitude'] ??= $previous->latitude;
            $validated['longitude'] ??= $previous->longitude;
            $validated['geofence_radius'] ??= $previous->geofence_radius;
            $validated['required_years'] ??= $previous->required_years;
        }

        $config = $event->qrConfigurations()->create($validated);

        $this->eventService->log($event, $request->user(), 'qr_created', [
            'config_id' => $config->id,
            'type' => $config->type,
            'valid_from' => $config->valid_from,
            'valid_until' => $config->valid_until,
        ]);

        return redirect()->route('admin.events.qr', $event)->with('success', 'QR configuration saved.');
    }

    public function update(Request $request, Event $event, QrConfiguration $config): RedirectResponse
    {
        $this->authorizeEvent($request->user(), $event);
        abort_unless($config->event_id === $event->id, 404);

        $validated = $request->validate([
            'type' => ['string', 'in:time_in,time_out'],
            'valid_from' => ['date_format:H:i'],
            'valid_until' => ['date_format:H:i', 'after:valid_from'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geofence_radius' => ['nullable', 'integer', 'min:1'],
            'required_years' => ['nullable', 'array'],
        ]);

        $config->update($validated);

        $this->eventService->log($event, $request->user(), 'qr_updated', [
            'config_id' => $config->id,
            'type' => $config->type,
        ]);

        return redirect()->route('admin.events.qr', $event)->with('success', 'QR configuration updated.');
    }

    public function generate(Request $request, Event $event, QrConfiguration $config): RedirectResponse
    {
        $this->authorizeEvent($request->user(), $event);
        abort_unless($config->event_id === $event->id, 404);

        $payload = $this->buildPayload($event, $config);

        $encrypted = $this->qrCodeService->encryptPayload($payload);
        $qrSvg = $this->qrCodeService->generateQrSvg($encrypted);

        $config->update([
            'qr_data' => $qrSvg,
            'is_generated' => true,
        ]);

        $this->eventService->log($event, $request->user(), 'qr_generated', [
            'config_id' => $config->id,
            'type' => $config->type,
        ]);

        return redirect()->route('admin.events.qr', $event)->with('success', 'QR code generated.');
    }

    public function download(Request $request, Event $event, QrConfiguration $config): \Illuminate\Http\Response
    {
        $this->authorizeEvent($request->user(), $event);
        abort_unless($config->event_id === $event->id, 404);

        $qr = $config->qr_data;
        if (!$qr) {
            $qr = $this->qrCodeService->generateQrSvg(
                $this->qrCodeService->encryptPayload($this->buildPayload($event, $config))
            );
        }

        $pdf = Pdf::loadView('pdf.qr-config', [
            'event' => $event,
            'date' => $event->event_date?->format('F j, Y'),
            'timeRange' => trim($this->formatTime($event->time_from) . ' - ' . $this->formatTime($event->time_to), ' -'),
            'type' => $config->type === 'time_in' ? 'Time In' : 'Time Out',
            'validRange' => trim($this->formatTime($config->valid_from) . ' - ' . $this->formatTime($config->valid_until), ' -'),
            'qr' => $qr,
        ]);

        $filename = Str::slug($event->title ?: 'qr') . '-qr-' . $config->id . '.pdf';

        return $pdf->download($filename);
    }

    public function destroy(Request $request, Event $event, QrConfiguration $config): RedirectResponse
    {
        $this->authorizeEvent($request->user(), $event);
        abort_unless($config->event_id === $event->id, 404);

        Attendance::where('qr_configuration_id', $config->id)->delete();
        $config->delete();

        $this->eventService->log($event, $request->user(), 'qr_deleted', [
            'config_id' => $config->id,
            'type' => $config->type,
        ]);

        return redirect()->route('admin.events.qr', $event)->with('success', 'QR configuration removed.');
    }

    private function buildPayload(Event $event, QrConfiguration $config): array
    {
        $date = $event->event_date->format('Y-m-d');
        $validFrom = Carbon::createFromFormat('Y-m-d H:i', "{$date} " . substr($config->valid_from, 0, 5), 'Asia/Manila');
        $validUntil = Carbon::createFromFormat('Y-m-d H:i', "{$date} " . substr($config->valid_until, 0, 5), 'Asia/Manila');

        return [
            'event_id' => $event->id,
            'qr_config_id' => $config->id,
            'type' => $config->type,
            'event_title' => $event->title,
            'event_date' => $event->event_date->format('Y-m-d'),
            'time_from' => $event->time_from,
            'time_to' => $event->time_to,
            'valid_time_from' => $config->valid_from,
            'valid_time_until' => $config->valid_until,
            'venue' => $event->venue,
            'valid_from' => $validFrom->setTimezone('UTC')->toIso8601String(),
            'valid_until' => $validUntil->setTimezone('UTC')->toIso8601String(),
            'latitude' => $config->latitude,
            'longitude' => $config->longitude,
            'geofence_radius' => $config->geofence_radius,
            'issued_at' => now()->toIso8601String(),
        ];
    }

    private function formatTime(?string $time): string
    {
        if (!$time) {
            return '';
        }

        $parts = explode(':', $time);
        $hour = (int) ($parts[0] ?? 0);
        $minute = (int) ($parts[1] ?? 0);
        $ampm = $hour >= 12 ? 'PM' : 'AM';
        $hour12 = $hour % 12 ?: 12;

        return sprintf('%02d:%02d %s', $hour12, $minute, $ampm);
    }

    private function authorizeEvent($user, Event $event): void
    {
        if (! $this->accessScopeService->isWithinScope($user, $event->organization)) {
            abort(403);
        }
    }
}

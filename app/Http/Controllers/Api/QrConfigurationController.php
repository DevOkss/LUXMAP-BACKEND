<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\QrConfiguration;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class QrConfigurationController extends Controller
{
    public function __construct(
        private QrCodeService $qrCodeService
    ) {}

    public function index(Event $event): JsonResponse
    {
        Gate::authorize('view', $event);
        $configs = $event->qrConfigurations()->orderBy('type')->get();

        return response()->json(['data' => $configs]);
    }

    public function store(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('update', $event);
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

        $config = $event->qrConfigurations()->create([
            ...$validated,
            'event_id' => $event->id,
        ]);

        return response()->json(['data' => $config], 201);
    }

    public function update(Request $request, Event $event, QrConfiguration $config): JsonResponse
    {
        Gate::authorize('update', $event);
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

        return response()->json(['data' => $config->fresh()]);
    }

    public function generate(Event $event, QrConfiguration $config): JsonResponse
    {
        Gate::authorize('update', $event);
        abort_unless($config->event_id === $event->id, 404);
        $date = $event->event_date->format('Y-m-d');
        $validFrom = Carbon::createFromFormat('Y-m-d H:i', "{$date} " . substr($config->valid_from, 0, 5), 'Asia/Manila');
        $validUntil = Carbon::createFromFormat('Y-m-d H:i', "{$date} " . substr($config->valid_until, 0, 5), 'Asia/Manila');

        $payload = [
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

        $encrypted = $this->qrCodeService->encryptPayload($payload);
        $qrSvg = $this->qrCodeService->generateQrSvg($encrypted);

        $config->update([
            'qr_data' => $qrSvg,
            'is_generated' => true,
        ]);

        return response()->json([
            'data' => [
                'config' => $config->fresh(),
                'qr_svg' => $qrSvg,
            ],
        ]);
    }

    public function destroy(Event $event, QrConfiguration $config): JsonResponse
    {
        Gate::authorize('update', $event);
        abort_unless($config->event_id === $event->id, 404);

        Attendance::where('qr_configuration_id', $config->id)->delete();
        $config->delete();

        return response()->json(null, 204);
    }

    public function last(Event $event): JsonResponse
    {
        Gate::authorize('view', $event);
        $last = $event->qrConfigurations()->latest()->first();

        return response()->json(['data' => $last]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DeviceBindingException;
use App\Http\Controllers\Controller;
use App\Models\DeviceTransferRequest;
use App\Services\DeviceBindingService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function __construct(
        private DeviceBindingService $deviceService,
        private NotificationService $notificationService,
    ) {}

    private function fingerprint(Request $request): string
    {
        return (string) $request->header(DeviceBindingService::FINGERPRINT_HEADER, '');
    }

    private function currentMeta(Request $request): ?array
    {
        // Try header X-Device-Meta (JSON) first, then query/body device_meta
        $header = $request->header('X-Device-Meta');
        if ($header) {
            $decoded = json_decode($header, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        $meta = $request->input('device_meta');
        if (is_array($meta)) {
            return $meta;
        }
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            if (is_array($decoded)) return $decoded;
        }
        return null;
    }

    /**
     * Whether the account has a bound device, and which fingerprint it is.
     * Hybrid: also returns trusted list and similarity hint for same-device cross-browser.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $fp = $this->fingerprint($request);
        $meta = $this->currentMeta($request) ?? $request->input('device_meta');
        // If meta still null, try to get from request header JSON or fallback
        if (is_string($meta)) {
            $meta = json_decode($meta, true);
        }
        $details = $this->deviceService->getStatusDetails($user, $fp, is_array($meta) ? $meta : null);
        $binding = $details['binding'];

        return response()->json([
            'fingerprint' => $fp,
            'binding' => $binding ? [
                'device_fingerprint' => $binding->device_fingerprint,
                'device_meta' => $binding->device_meta,
                'bound_at' => $binding->bound_at?->toDateTimeString(),
            ] : null,
            'trusted_fingerprints' => $details['trusted_fingerprints'],
            'is_trusted' => $details['is_trusted'],
            'is_similar' => $details['is_similar'],
        ]);
    }

    public function bind(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_fingerprint' => ['required', 'string', 'min:8'],
            'device_meta' => ['sometimes', 'array'],
        ]);

        try {
            $binding = $this->deviceService->bindDevice(
                $request->user(),
                $data['device_fingerprint'],
                $data['device_meta'] ?? [],
            );
        } catch (DeviceBindingException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json([
            'message' => 'Device bound',
            'binding' => [
                'device_fingerprint' => $binding->device_fingerprint,
                'device_meta' => $binding->device_meta,
                'bound_at' => $binding->bound_at?->toDateTimeString(),
            ],
        ], 201);
    }

    /**
     * Face-verified instant bind for same physical device but different browser/incognito.
     * Bypasses old-device approval if hardware is similar and face is enrolled.
     */
    public function bindFaceVerified(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_fingerprint' => ['required', 'string', 'min:8'],
            'device_meta' => ['sometimes', 'array'],
        ]);

        try {
            $binding = $this->deviceService->bindWithFaceVerified(
                $request->user(),
                $data['device_fingerprint'],
                $data['device_meta'] ?? [],
            );
        } catch (DeviceBindingException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json([
            'message' => 'Device bound via face verification (same hardware)',
            'binding' => [
                'device_fingerprint' => $binding->device_fingerprint,
                'device_meta' => $binding->device_meta,
                'bound_at' => $binding->bound_at?->toDateTimeString(),
                'trusted_fingerprints' => $binding->trusted_fingerprints ?? [],
            ],
        ], 200);
    }

    public function transferRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_fingerprint' => ['required', 'string', 'min:8'],
            'device_meta' => ['sometimes', 'array'],
        ]);

        try {
            $transfer = $this->deviceService->requestTransfer(
                $request->user(),
                $data['device_fingerprint'],
                $data['device_meta'] ?? [],
            );
        } catch (DeviceBindingException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        $this->notificationService->notifyUser(
            $request->user(),
            'New device transfer request',
            'A new device is asking to take over your device binding. Open Security to approve or reject it.',
            ['type' => 'device_transfer', 'url' => '/security'],
        );

        return response()->json([
            'message' => 'Transfer requested',
            'request' => $this->transferPayload($transfer, $this->fingerprint($request)),
        ], 201);
    }

    public function transferRequests(Request $request): JsonResponse
    {
        $requests = DeviceTransferRequest::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        $fingerprint = $this->fingerprint($request);

        return response()->json([
            'requests' => $requests->map(fn (DeviceTransferRequest $transfer) => $this->transferPayload($transfer, $fingerprint))->values(),
        ]);
    }

    public function approve(Request $request, DeviceTransferRequest $transfer): JsonResponse
    {
        try {
            $this->deviceService->approveTransfer(
                $request->user(),
                $transfer,
                $this->fingerprint($request),
            );
        } catch (DeviceBindingException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        $this->notificationService->notifyUser(
            $request->user(),
            'Device transfer approved',
            'Your device binding moved to the new device. You may sign in there now.',
            ['type' => 'device_transfer', 'url' => '/security'],
        );

        return response()->json(['message' => 'Transfer approved']);
    }

    public function reject(Request $request, DeviceTransferRequest $transfer): JsonResponse
    {
        try {
            $this->deviceService->rejectTransfer(
                $request->user(),
                $transfer,
                $this->fingerprint($request),
            );
        } catch (DeviceBindingException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json(['message' => 'Transfer rejected']);
    }

    private function transferPayload(DeviceTransferRequest $transfer, string $fingerprint): array
    {
        return [
            'id' => $transfer->id,
            'user_id' => $transfer->user_id,
            'requesting_fingerprint' => $transfer->requesting_fingerprint,
            'requesting_meta' => $transfer->requesting_meta,
            'status' => $transfer->status,
            'requested_at' => $transfer->requested_at?->toDateTimeString(),
            'decided_at' => $transfer->decided_at?->toDateTimeString(),
            'decided_by_fingerprint' => $transfer->decided_by_fingerprint,
            'direction' => $transfer->requesting_fingerprint === $fingerprint ? 'outgoing' : 'incoming',
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Event;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    private string $hmacKey;

    public function __construct()
    {
        $this->hmacKey = config('services.qr_key') ?? config('app.key');
    }

    public function encryptPayload(array $payload): string
    {
        $json = json_encode($payload);
        $iv = random_bytes(16);
        $key = hex2bin($this->hmacKey);
        $encrypted = openssl_encrypt($json, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv . $encrypted);
    }

    public function decryptPayload(string $encrypted): ?array
    {
        $data = base64_decode($encrypted);
        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        $key = hex2bin($this->hmacKey);

        $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            return null;
        }

        return json_decode($decrypted, true);
    }

    public function generateQrSvg(string $content): string
    {
        $svg = QrCode::format('svg')
            ->size(300)
            ->errorCorrection('M')
            ->generate($content);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function generatePayload(Event $event): array
    {
        return [
            'event_id'   => $event->id,
            'org_id'     => $event->organization_id,
            'secret'     => $event->qr_secret,
            'issued_at'  => now()->toIso8601String(),
            'expires_at' => $event->attendance_end?->toIso8601String() ?? now()->addDay()->toIso8601String(),
        ];
    }

    public function signPayload(array $payload): string
    {
        $json = json_encode($payload);
        $payload['signature'] = hash_hmac('sha256', $json, $this->hmacKey);
        return json_encode($payload);
    }

    public function verifySignature(string $jsonPayload): ?array
    {
        $payload = json_decode($jsonPayload, true);

        if (!$payload || !isset($payload['signature'])) {
            return null;
        }

        $signature = $payload['signature'];
        unset($payload['signature']);

        $expected = hash_hmac('sha256', json_encode($payload), $this->hmacKey);

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload['signature'] = $signature;
        return $payload;
    }

    public function generateSvg(Event $event): string
    {
        $payload = $this->generatePayload($event);
        $signed = $this->signPayload($payload);

        return QrCode::format('svg')
            ->size(300)
            ->errorCorrection('M')
            ->generate($signed);
    }

    public function generateBase64(Event $event): string
    {
        $payload = $this->generatePayload($event);
        $signed = $this->signPayload($payload);

        $svg = QrCode::format('svg')
            ->size(300)
            ->errorCorrection('M')
            ->generate($signed);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}

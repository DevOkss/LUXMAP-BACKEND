<?php

namespace App\Services;

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

        return base64_encode($iv.$encrypted);
    }

    public function generateQrSvg(string $content): string
    {
        $svg = QrCode::format('svg')
            ->size(300)
            ->errorCorrection('M')
            ->generate($content);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}

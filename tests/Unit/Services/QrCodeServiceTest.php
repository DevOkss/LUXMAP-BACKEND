<?php

use App\Models\Event;
use App\Services\QrCodeService;

beforeEach(function () {
    $this->service = app(QrCodeService::class);
});

test('generates valid qr payload for event', function () {
    $event = Event::factory()->create();
    $payload = $this->service->generatePayload($event);

    expect($payload)->toBeArray();
    expect($payload)->toHaveKeys(['event_id', 'org_id', 'secret', 'issued_at', 'expires_at']);
    expect($payload['event_id'])->toBe($event->id);
    expect($payload['org_id'])->toBe($event->organization_id);
    expect($payload['secret'])->toBe($event->qr_secret);
});

test('signs and verifies payload', function () {
    $event = Event::factory()->create();
    $payload = $this->service->generatePayload($event);
    $signed = $this->service->signPayload($payload);

    $verified = $this->service->verifySignature($signed);

    expect($verified)->not->toBeNull();
    expect($verified['event_id'])->toBe($event->id);
});

test('fails verification with wrong signature', function () {
    $result = $this->service->verifySignature(
        json_encode(['event_id' => 1, 'org_id' => 1, 'signature' => 'invalid'])
    );

    expect($result)->toBeNull();
});

test('generates svg qr code', function () {
    $event = Event::factory()->create();
    $svg = $this->service->generateSvg($event);

    expect($svg)->toBeString();
    expect($svg)->toContain('<svg');
});

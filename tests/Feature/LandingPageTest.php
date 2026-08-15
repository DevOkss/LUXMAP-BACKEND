<?php

test('the app landing page is public and renders the PWA install content', function () {
    config(['services.pwa.url' => 'http://192.168.1.113:9000']);

    $this->get('/app')
        ->assertOk()
        ->assertSee('LuxMap — Install the App', false)
        ->assertSee('http://192.168.1.113:9000', false)
        ->assertSee('Install App', false)
        ->assertSee('beforeinstallprompt', false)
        ->assertSee('QR Attendance', false)
        ->assertSee('How to install', false)
        ->assertSee('dashboard.png', false);
});

test('the app landing page reflects a configurable PWA url', function () {
    config(['services.pwa.url' => 'http://10.0.0.5:9000']);

    $this->get('/app')
        ->assertOk()
        ->assertSee('http://10.0.0.5:9000', false);
});

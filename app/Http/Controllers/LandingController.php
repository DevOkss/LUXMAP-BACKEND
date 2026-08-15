<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Public landing page that introduces the LuxMap student PWA and lets students
 * install it directly from the browser (no App Store / Play Store).
 */
class LandingController extends Controller
{
    public function app(): View
    {
        return view('pwa-landing', [
            'pwaUrl' => config('services.pwa.url'),
            'appName' => config('app.name', 'LuxMap'),
            'features' => [
                [
                    'title' => 'QR Attendance',
                    'description' => 'Scan event QR codes to record attendance — even offline. It syncs automatically when you are back online.',
                    'icon' => 'qr',
                ],
                [
                    'title' => 'Face Verification',
                    'description' => 'Secure, on-device face verification with blink detection for every attendance scan.',
                    'icon' => 'face',
                ],
                [
                    'title' => 'Fees & Payments',
                    'description' => 'View your outstanding fees and penalties, track payments, and keep your receipts.',
                    'icon' => 'payments',
                ],
                [
                    'title' => 'Notifications',
                    'description' => 'Get alerts for new events, fee postings, and payment updates right on your phone.',
                    'icon' => 'bell',
                ],
            ],
            'steps' => [
                ['title' => 'Open the app', 'description' => 'Tap Open App below to load LuxMap in your browser.'],
                ['title' => 'Install it', 'description' => 'Use the Install button, or your browser\'s "Add to Home Screen" option.'],
                ['title' => 'Sign in & go', 'description' => 'Log in once, and LuxMap is ready — attendance and payments at your fingertips.'],
            ],
            'screens' => [
                ['src' => '/storage/images/dashboard.png', 'alt' => 'LuxMap dashboard'],
                ['src' => '/storage/images/fee-page.png', 'alt' => 'LuxMap fees & payments'],
                ['src' => '/storage/images/dashboard-slant.png', 'alt' => 'LuxMap attendance'],
            ],
        ]);
    }
}

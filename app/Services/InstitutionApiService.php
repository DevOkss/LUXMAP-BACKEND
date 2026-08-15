<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class InstitutionApiService
{
    /**
     * Authenticate against the institution student portal API.
     *
     * When a real institution endpoint is configured, the credentials are
     * verified over HTTP. Otherwise the local institution account records are
     * used directly (avoids a self-HTTP deadlock under the single-threaded
     * `php artisan serve` development server).
     *
     * Returns the student record on success, or null on failure.
     */
    public function authenticate(string $studId, string $password): ?array
    {
        $endpoint = config('services.institution.endpoint');

        if (!$endpoint) {
            return app(InstitutionAccountService::class)->authenticate($studId, $password);
        }

        try {
            $response = Http::timeout(10)->post($endpoint, [
                'stud_id' => $studId,
                'password' => $password,
            ]);
        } catch (\Throwable) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }
}

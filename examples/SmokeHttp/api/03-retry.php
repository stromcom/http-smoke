<?php

declare(strict_types=1);

use Stromcom\HttpSmoke\Definition\Suite;

return static function (Suite $suite): void {
    // Unique key per run so the eventual-consistency endpoint resets cleanly.
    $eventuallyKey = 'k' . bin2hex(random_bytes(3));

    $suite->group('api.retry', maxFailures: 3)
        ->baseUrl('{API_BASE_URL}')

        // First 2 hits return 404, the 3rd returns 200.
        // retryOnFailure(2, 30) → up to 2 retries (3 attempts total) with 30 ms delay.
        ->get('/eventually/' . $eventuallyKey . '/2')
            ->label('GET /eventually/<k>/2 – retry-on-failure (3 attempts, eventual 200)')
            ->retryOnFailure(2, 30)
            ->expectStatus(200)
            ->expectJsonPath('status', 'ready')

        // First call returns 500, second returns 200 (legacy 5xx-only retry).
        ->get('/fail-once')
            ->label('GET /fail-once – retry-on-5xx recovers on 2nd attempt')
            ->retryOn5xx(1)
            ->expectStatus(200)
            ->expectJsonPath('status', 'ok')

        // Slow endpoint (300 ms) — verifies a generous timeout still passes.
        ->get('/slow?ms=300')
            ->label('GET /slow?ms=300 – completes within 5 s timeout')
            ->timeout(5)
            ->expectStatus(200)
            ->expectJsonPath('slept_ms', 300);
};

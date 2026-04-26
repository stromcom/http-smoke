<?php

declare(strict_types=1);

use Stromcom\HttpSmoke\Definition\Suite;

return static function (Suite $suite): void {
    $suite->group('api.public', maxFailures: 3)
        ->baseUrl('{API_BASE_URL}')

        ->get('/ping')
            ->label('GET /api/ping – public ping')
            ->expectStatus(200)
            ->expectJson()
            ->expectJsonPath('status', 'ok')
            ->expectJsonHasKeys(['pong', 'time'])

        ->get('/version')
            ->label('GET /api/version – version metadata')
            ->expectStatus(200)
            ->expectJson()
            ->expectJsonHasKeys(['version', 'commit'])

        ->get('/users/')
            ->label('GET /api/users/ – without auth → 401')
            ->expectStatus(401)
            ->expectJson()
            ->expectJsonPath('status', 'error');

    $suite->group('api.authenticated', maxFailures: 3)
        ->baseUrl('{API_BASE_URL}')
        ->header('Authorization', '{API_BEARER_TOKEN}')

        ->get('/users/')
            ->label('GET /api/users/ – list with auth')
            ->expectStatus(200)
            ->expectJson()
            ->expectJsonPath('status', 'success')
            ->expectJsonHasKeys(['data', 'meta.count'])
            ->expectJsonPath('meta.count', 2);
};

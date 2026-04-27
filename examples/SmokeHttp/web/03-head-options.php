<?php

declare(strict_types=1);

use Stromcom\HttpSmoke\Definition\Suite;

return static function (Suite $suite): void {
    $suite->group('web.head-options', maxFailures: 3)
        ->baseUrl('{APP_BASE_URL}')

        ->head('/')
            ->label('HEAD / – headers only, no body')
            ->expectStatus(200)
            ->expectHeaderContains('Cache-Control', 'max-age')

        ->head('/this-page-does-not-exist')
            ->label('HEAD /missing → 404')
            ->expectStatus(404)

        ->options('/')
            ->label('OPTIONS / – CORS preflight, expect Allow header')
            ->expectStatus(204)
            ->expectHeaderContains('Allow', 'GET');
};

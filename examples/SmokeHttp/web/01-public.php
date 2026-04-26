<?php

declare(strict_types=1);

use Stromcom\HttpSmoke\Definition\Suite;

return static function (Suite $suite): void {
    $suite->group('web.public', maxFailures: 3)
        ->baseUrl('{APP_BASE_URL}')

        ->get('/')
            ->label('GET / – HTML home, expect <title> and Cache-Control')
            ->expectStatus(200)
            ->expectContains('<title>Smoke Demo</title>')
            ->expectHtmlElement('title', 'Smoke Demo')
            ->expectHtmlElement('h1', 'Welcome')
            ->expectHeaderContains('Cache-Control', 'max-age')

        ->get('/robots.txt')
            ->label('GET /robots.txt – plain text')
            ->expectStatus(200)
            ->expectContains('User-agent')

        ->get('/redirect-to-login')
            ->label('GET /redirect-to-login → 302 /login')
            ->expectRedirect('/login')

        ->get('/login')
            ->label('GET /login – form page')
            ->expectStatus(200)
            ->expectContains('<form')

        ->get('/this-page-does-not-exist')
            ->label('GET /missing → 404')
            ->expectStatus(404);
};

<?php

declare(strict_types=1);

use Stromcom\HttpSmoke\Definition\Suite;

return static function (Suite $suite): void {
    $suite->group('web.session', maxFailures: 5)
        ->baseUrl('{APP_BASE_URL}')

        // Without a session cookie the protected endpoint must return 401.
        ->get('/session/dashboard')
            ->label('GET /session/dashboard – no cookie → 401')
            ->expectStatus(401)
            ->expectJsonPath('status', 'error')

        // Session chain: log in, follow up with cookie-protected requests, log out.
        ->session('login-flow')
            ->post('/session/login', ['username' => 'demo', 'password' => 'demo'])
                ->label('POST /session/login – sets cookie, redirects to /session/dashboard')
                ->expectRedirect('/session/dashboard')

            ->get('/session/dashboard')
                ->label('GET /session/dashboard – with cookie → 200')
                ->expectStatus(200)
                ->expectJsonPath('status', 'success')
                ->expectJsonHasKeys(['session'])

            ->get('/session/projects')
                ->label('GET /session/projects – cookie still present')
                ->expectStatus(200)
                ->expectJsonPath('page', '/session/projects')

            ->post('/session/logout')
                ->label('POST /session/logout – 204')
                ->expectStatus(204)
        ->endSession();
};

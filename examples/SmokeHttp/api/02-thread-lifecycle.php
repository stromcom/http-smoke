<?php

declare(strict_types=1);

use Stromcom\HttpSmoke\Definition\Suite;

return static function (Suite $suite): void {
    $threadCode = 'smoke-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));

    $suite->group('api.threads', maxFailures: 5)
        ->baseUrl('{API_BASE_URL}')
        ->header('Authorization', '{API_BEARER_TOKEN}')
        ->session('thread-lifecycle')
        ->defaultAsJson()

        ->post('/threads/', [
            'thread_code' => $threadCode,
            'message' => '<p>Hello from smoke tests</p>',
        ])
            ->label('POST /threads/ – create thread (capture hash)')
            ->expectStatus(201)
            ->expectJsonPath('status', 'success')
            ->expectJsonHasKeys(['data.thread.hash', 'data.message.hash'])
            ->captureJsonPath('threadHash', 'data.thread.hash')
            ->captureJsonPath('messageHash', 'data.message.hash')

        ->get('/threads/{@threadHash}/')
            ->label('GET /threads/{hash}/ – read just-created thread')
            ->expectStatus(200)
            ->expectJsonPath('status', 'success')
            ->expectJsonPath('data.code', $threadCode)

        ->patch('/threads/{@threadHash}/messages/{@messageHash}/notice/', ['action' => 'read'])
            ->label('PATCH notice/ – mark message as read')
            ->expectStatus(200)
            ->expectJsonPath('status', 'success')

        ->get('/threads/{@threadHash}/')
            ->label('GET /threads/{hash}/ – verify thread still present after PATCH')
            ->expectStatus(200)
            ->expectJsonPath('status', 'success')
            ->expectJsonHasKeys(['data.messages'])

        ->delete('/threads/{@threadHash}/')
            ->label('DELETE /threads/{hash}/ – cleanup')
            ->expectStatus(204)

        ->get('/threads/{@threadHash}/')
            ->label('GET /threads/{hash}/ – gone → 404')
            ->expectStatus(404);
};

<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Http;

interface HttpClientInterface
{
    public function send(Request $request): Response;

    /**
     * @param list<Request> $requests
     * @return list<Response>  Same length and order as $requests.
     */
    public function sendMany(array $requests): array;
}

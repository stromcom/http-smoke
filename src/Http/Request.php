<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Http;

final readonly class Request
{
    /**
     * @param array<string, string>            $headers
     * @param array<array-key, mixed>|string|null $body  Array = encoded as JSON or form per $sendAsJson; string = raw body sent as-is.
     */
    public function __construct(
        public Method $method,
        public string $url,
        public array $headers = [],
        public array|string|null $body = null,
        public bool $sendAsJson = false,
        public int $timeoutSeconds = 10,
        public ?string $cookieJarPath = null,
        public bool $insecureTls = false,
        public string $userAgent = 'StromcomSmokeTest/1.0',
    ) {}

    public function withUrl(string $url): self
    {
        return new self(
            method: $this->method,
            url: $url,
            headers: $this->headers,
            body: $this->body,
            sendAsJson: $this->sendAsJson,
            timeoutSeconds: $this->timeoutSeconds,
            cookieJarPath: $this->cookieJarPath,
            insecureTls: $this->insecureTls,
            userAgent: $this->userAgent,
        );
    }

    /**
     * @param array<array-key, mixed>|string|null $body
     */
    public function withBody(array|string|null $body): self
    {
        return new self(
            method: $this->method,
            url: $this->url,
            headers: $this->headers,
            body: $body,
            sendAsJson: $this->sendAsJson,
            timeoutSeconds: $this->timeoutSeconds,
            cookieJarPath: $this->cookieJarPath,
            insecureTls: $this->insecureTls,
            userAgent: $this->userAgent,
        );
    }
}

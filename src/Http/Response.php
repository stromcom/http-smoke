<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Http;

final readonly class Response
{
    /**
     * @param array<string, string> $headers Lowercase header names.
     */
    public function __construct(
        public int $statusCode,
        public string $body,
        public array $headers,
        public float $durationSeconds,
        public ?string $transportError = null,
    ) {}

    public function isTransportError(): bool
    {
        return $this->transportError !== null;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->getHeader('location');
    }

    public static function transportFailure(string $error, float $durationSeconds): self
    {
        return new self(
            statusCode: 0,
            body: '',
            headers: [],
            durationSeconds: $durationSeconds,
            transportError: $error,
        );
    }
}

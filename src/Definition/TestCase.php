<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Definition;

use Stromcom\HttpSmoke\Assertion\AssertionInterface;
use Stromcom\HttpSmoke\Capture\CaptureInterface;
use Stromcom\HttpSmoke\Http\Method;

final readonly class TestCase
{
    /**
     * @param array<string, string>            $headers
     * @param array<array-key, mixed>|string|null $body
     * @param list<AssertionInterface>         $assertions
     * @param list<CaptureInterface>           $captures
     */
    public function __construct(
        public string $group,
        public Method $method,
        public string $url,
        public array $headers = [],
        public array|string|null $body = null,
        public bool $sendAsJson = false,
        public int $timeoutSeconds = 10,
        public int $retryOnFailure = 0,
        public int $retryDelayMs = 50,
        public int $retryOn5xx = 0,
        public ?string $label = null,
        public ?string $sessionId = null,
        public array $assertions = [],
        public array $captures = [],
    ) {}

    public function describe(): string
    {
        return $this->label ?? "{$this->method->value} {$this->url}";
    }
}

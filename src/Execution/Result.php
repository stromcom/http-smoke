<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Execution;

use Stromcom\HttpSmoke\Definition\TestCase;
use Stromcom\HttpSmoke\Http\Response;

final class Result
{
    /** @var list<string> */
    public array $failures = [];

    public int $attempts = 1;

    public float $totalDurationSeconds;

    /**
     * @param list<string> $failures
     */
    private function __construct(
        public readonly TestCase $case,
        public readonly Response $response,
        public readonly bool $skipped,
        public readonly ?string $skipReason,
        array $failures = [],
    ) {
        $this->failures = $failures;
        $this->totalDurationSeconds = $response->durationSeconds;
    }

    /**
     * @param list<string> $failures
     */
    public static function from(TestCase $case, Response $response, array $failures): self
    {
        return new self($case, $response, false, null, $failures);
    }

    public static function skipped(TestCase $case, string $reason): self
    {
        return new self(
            $case,
            new Response(0, '', [], 0.0, $reason),
            true,
            $reason,
        );
    }

    public function setRetryMetadata(int $attempts, float $totalDurationSeconds): void
    {
        $this->attempts = max(1, $attempts);
        $this->totalDurationSeconds = max($this->response->durationSeconds, $totalDurationSeconds);
    }

    public function isSkipped(): bool
    {
        return $this->skipped;
    }

    public function isPassed(): bool
    {
        return !$this->skipped
            && !$this->response->isTransportError()
            && $this->failures === [];
    }

    public function isFailed(): bool
    {
        return !$this->skipped && !$this->isPassed();
    }
}

<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Reporting;

use Stromcom\HttpSmoke\Exception\ConfigException;
use Stromcom\HttpSmoke\Execution\Report;
use Stromcom\HttpSmoke\Execution\Result;

final class JsonReporter implements ReporterInterface
{
    public const int SCHEMA_VERSION = 2;

    /** @var array<string, mixed>|null */
    private ?array $lastData = null;

    public function __construct(
        private readonly ?string $outputPath = null,
        private readonly ?string $environment = null,
        private readonly int $concurrency = 1,
    ) {}

    public function onStart(array $groups, int $totalTests): void {}

    public function onResult(Result $result, int $current, int $total): void {}

    public function onEnd(Report $report): void
    {
        $this->lastData = $this->generate($report);
        if ($this->outputPath !== null) {
            $this->writeToFile($this->lastData, $this->outputPath);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLastData(): ?array
    {
        return $this->lastData;
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(Report $report): array
    {
        return [
            'meta' => $this->buildMeta($report),
            'groups' => $this->buildGroups($report),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function writeToFile(array $data, string $filePath): void
    {
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        $dir = dirname($filePath);
        if (!is_dir($dir) && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new ConfigException("Failed to create directory: {$dir}");
        }

        if (file_put_contents($filePath, $json . "\n") === false) {
            throw new ConfigException("Failed to write JSON report: {$filePath}");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMeta(Report $report): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'environment' => $this->environment,
            'generated_at' => date('c'),
            'duration_s' => round($report->getTotalDuration(), 3),
            'concurrency' => $this->concurrency,
            'summary' => [
                'total' => $report->getTotalCount(),
                'passed' => $report->getPassedCount(),
                'failed' => $report->getFailedCount(),
                'skipped' => $report->getSkippedCount(),
                'success' => $report->isSuccessful(),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildGroups(Report $report): array
    {
        $groups = [];
        foreach ($report->getResultsByGroup() as $name => $results) {
            $passed = count(array_filter($results, static fn(Result $r): bool => $r->isPassed()));
            $failed = count(array_filter($results, static fn(Result $r): bool => $r->isFailed()));
            $skipped = count(array_filter($results, static fn(Result $r): bool => $r->isSkipped()));

            $groups[] = [
                'name' => $name,
                'summary' => [
                    'total' => count($results),
                    'passed' => $passed,
                    'failed' => $failed,
                    'skipped' => $skipped,
                ],
                'tests' => $this->buildTests($results),
            ];
        }

        return $groups;
    }

    /**
     * @param list<Result> $results
     * @return list<array<string, mixed>>
     */
    private function buildTests(array $results): array
    {
        $tests = [];
        /** @var array<string, list<array<string, mixed>>> $sessionHistory */
        $sessionHistory = [];

        foreach ($results as $result) {
            $sessionId = $result->case->sessionId;
            $entry = $this->buildEntry($result, self::extractSessionLabel($sessionId));

            if ($sessionId !== null) {
                $entry['chain_context'] = $sessionHistory[$sessionId] ?? [];
                $sessionHistory[$sessionId][] = $this->buildChainStep($result);
            } else {
                $entry['chain_context'] = null;
            }

            $tests[] = $entry;
        }

        return $tests;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEntry(Result $result, ?string $sessionLabel): array
    {
        return [
            'label' => $result->case->describe(),
            'method' => $result->case->method->value,
            'url' => $result->case->url,
            'status' => $result->isSkipped() ? 'skipped' : ($result->isPassed() ? 'passed' : 'failed'),
            'http_code' => $result->response->statusCode > 0 ? $result->response->statusCode : null,
            'duration_ms' => (int) round($result->response->durationSeconds * 1000),
            'attempts' => $result->attempts,
            'total_duration_ms' => (int) round($result->totalDurationSeconds * 1000),
            'session' => $sessionLabel !== null ? ['label' => $sessionLabel] : null,
            'failures' => $result->failures,
            'skip_reason' => $result->isSkipped() ? $result->skipReason : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildChainStep(Result $result): array
    {
        return [
            'label' => $result->case->describe(),
            'method' => $result->case->method->value,
            'url' => $result->case->url,
            'status' => $result->isSkipped() ? 'skipped' : ($result->isPassed() ? 'passed' : 'failed'),
            'http_code' => $result->response->statusCode > 0 ? $result->response->statusCode : null,
            'duration_ms' => (int) round($result->response->durationSeconds * 1000),
            'attempts' => $result->attempts,
            'total_duration_ms' => (int) round($result->totalDurationSeconds * 1000),
            'failures' => $result->failures,
        ];
    }

    private static function extractSessionLabel(?string $sessionId): ?string
    {
        if ($sessionId === null) {
            return null;
        }
        if (preg_match('/^sess_[a-f0-9]+_(.+)$/', $sessionId, $m) === 1) {
            return str_replace('_', ' ', $m[1]);
        }

        return 'Session';
    }
}

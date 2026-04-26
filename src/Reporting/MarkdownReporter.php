<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Reporting;

use Stromcom\HttpSmoke\Exception\ConfigException;
use Stromcom\HttpSmoke\Execution\Report;
use Stromcom\HttpSmoke\Execution\Result;
use Stromcom\HttpSmoke\Reporting\Support\DurationFormatter;

final class MarkdownReporter implements ReporterInterface
{
    private readonly JsonReporter $json;

    public function __construct(
        private readonly ?string $outputPath = null,
        ?JsonReporter $json = null,
        ?string $environment = null,
    ) {
        $this->json = $json ?? new JsonReporter(environment: $environment);
    }

    public function onStart(array $groups, int $totalTests): void {}

    public function onResult(Result $result, int $current, int $total): void {}

    public function onEnd(Report $report): void
    {
        if ($this->outputPath === null) {
            return;
        }
        $data = $this->json->generate($report);
        $markdown = $this->build($data);
        $this->write($markdown, $this->outputPath);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function build(array $data): string
    {
        $meta = self::asArray($data['meta'] ?? []);
        $groups = self::asArray($data['groups'] ?? []);
        $summary = self::asArray($meta['summary'] ?? []);

        $environment = self::asString($meta['environment'] ?? '');
        $total = self::asInt($summary['total'] ?? 0);
        $passed = self::asInt($summary['passed'] ?? 0);
        $failed = self::asInt($summary['failed'] ?? 0);
        $skipped = self::asInt($summary['skipped'] ?? 0);
        $success = (bool) ($summary['success'] ?? false);
        $duration = DurationFormatter::seconds(self::asFloat($meta['duration_s'] ?? 0));

        if ($success) {
            return "## ✅ Smoke Tests Passed\n\n"
                . "| | |\n|---|---|\n"
                . "| **Environment** | `{$environment}` |\n"
                . "| **Tests** | {$total} |\n"
                . "| **Duration** | {$duration} |\n\n"
                . "> ✔ All **{$total}** smoke tests passed.\n\n";
        }

        $md = "## ❌ Smoke Tests Failed\n\n"
            . "| | |\n|---|---|\n"
            . "| **Environment** | `{$environment}` |\n"
            . "| **Total** | {$total} |\n"
            . "| ✅ Passed | {$passed} |\n"
            . "| ❌ Failed | **{$failed}** |\n"
            . "| ⊘ Skipped | {$skipped} |\n"
            . "| **Duration** | {$duration} |\n\n";

        foreach ($groups as $group) {
            $group = self::asArray($group);
            $gSummary = self::asArray($group['summary'] ?? []);
            $gFailed = self::asInt($gSummary['failed'] ?? 0);
            $gSkipped = self::asInt($gSummary['skipped'] ?? 0);
            $icon = $gFailed > 0 ? '🔴' : ($gSkipped > 0 ? '🟡' : '🟢');
            $name = self::asString($group['name'] ?? '');

            $md .= "### {$icon} {$name}\n\n";
            $md .= "| Status | Test | HTTP | Duration | Details |\n";
            $md .= "|:---:|---|:---:|---:|---|\n";

            $inSession = false;
            $tests = self::asArray($group['tests'] ?? []);

            foreach ($tests as $test) {
                $test = self::asArray($test);
                $session = $test['session'] ?? null;

                if (is_array($session) && !$inSession) {
                    $sessionLabel = self::esc(self::asString($session['label'] ?? ''));
                    $md .= "| 🔒 | **{$sessionLabel}** *(shared cookies)* | | | |\n";
                    $inSession = true;
                } elseif ($session === null && $inSession) {
                    $md .= "| 🔓 | *session end* | | | |\n";
                    $inSession = false;
                }

                $label = self::esc(self::asString($test['label'] ?? ''));
                $http = self::httpDisplay($test['http_code'] ?? null);
                $dur = DurationFormatter::milliseconds(self::asInt($test['duration_ms'] ?? 0));
                $attempts = self::asInt($test['attempts'] ?? 1);
                if ($attempts > 1) {
                    $totalDur = DurationFormatter::milliseconds(self::asInt($test['total_duration_ms'] ?? 0));
                    $retries = $attempts - 1;
                    $dur .= "<br><sub>retried {$retries}× · total {$totalDur}</sub>";
                }
                $prefix = is_array($session) ? '↳ ' : '';
                $status = self::asString($test['status'] ?? '');

                if ($status === 'skipped') {
                    $md .= "| ⊘ | {$prefix}{$label} | — | — | Skipped |\n";
                } elseif ($status === 'passed') {
                    $md .= "| ✅ | {$prefix}{$label} | {$http} | {$dur} | — |\n";
                } else {
                    $details = $this->buildFailureDetails($test);
                    $md .= "| ❌ | {$prefix}{$label} | {$http} | {$dur} | {$details} |\n";
                }
            }

            if ($inSession) {
                $md .= "| 🔓 | *session end* | | | |\n";
            }
            $md .= "\n";
        }

        return $md;
    }

    /**
     * @param array<array-key, mixed> $test
     */
    private function buildFailureDetails(array $test): string
    {
        $parts = [];
        $chain = $test['chain_context'] ?? null;
        if (is_array($chain) && $chain !== []) {
            $steps = [];
            foreach ($chain as $step) {
                $step = self::asArray($step);
                $icon = ($step['status'] ?? null) === 'passed' ? '✔' : '✖';
                $method = self::asString($step['method'] ?? '');
                $code = self::httpDisplay($step['http_code'] ?? null);
                $steps[] = "{$icon} {$method} {$code}";
            }
            $method = self::asString($test['method'] ?? '');
            $code = self::httpDisplay($test['http_code'] ?? null);
            $steps[] = "✖ {$method} {$code}";
            $parts[] = 'chain: ' . implode(' → ', $steps);
        }

        $failures = self::asArray($test['failures'] ?? []);
        foreach ($failures as $f) {
            $parts[] = self::esc(mb_substr(self::asString($f), 0, 120));
        }

        return implode('<br>', $parts);
    }

    private function write(string $markdown, string $filePath): void
    {
        $dir = dirname($filePath);
        if (!is_dir($dir) && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new ConfigException("Failed to create directory: {$dir}");
        }
        if (file_put_contents($filePath, $markdown) === false) {
            throw new ConfigException("Failed to write Markdown report: {$filePath}");
        }
    }

    private static function esc(string $text): string
    {
        return str_replace(['|', "\n", "\r"], ['\\|', ' ', ''], $text);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private static function asString(mixed $value): string
    {
        return is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');
    }

    private static function asInt(mixed $value): int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
    }

    private static function asFloat(mixed $value): float
    {
        return is_float($value) || is_int($value) ? (float) $value : (is_numeric($value) ? (float) $value : 0.0);
    }

    private static function httpDisplay(mixed $value): string
    {
        if (is_int($value) && $value > 0) {
            return (string) $value;
        }

        return '—';
    }
}

<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Reporting;

use Stromcom\HttpSmoke\Execution\Report;
use Stromcom\HttpSmoke\Execution\Result;
use Stromcom\HttpSmoke\Reporting\Support\AnsiStyle;
use Stromcom\HttpSmoke\Reporting\Support\DurationFormatter;

final class ConsoleReporter implements ReporterInterface
{
    private const int VERBOSE_BODY_MAX = 2000;

    private ?string $currentGroup = null;

    private ?string $currentSession = null;

    /** @var array<string, int> */
    private array $groupSizes = [];

    public function __construct(
        private readonly bool $verbose = false,
        private ?AnsiStyle $style = null,
        private readonly ?string $environment = null,
        private readonly int $concurrency = 1,
    ) {
        $this->style ??= AnsiStyle::autodetect();
    }

    public function onStart(array $groups, int $totalTests): void
    {
        $style = $this->styleOrThrow();

        echo PHP_EOL;
        echo '  ' . $style->bold($style->magenta('Smoke Tests')) . PHP_EOL;
        echo $style->dim('  ' . str_repeat('─', 54)) . PHP_EOL;
        if ($this->environment !== null) {
            echo $style->dim('  Environment  ') . $style->bold($this->environment) . PHP_EOL;
        }
        echo $style->dim('  Tests        ') . "{$totalTests}" . PHP_EOL;
        echo $style->dim('  Concurrency  ') . "{$this->concurrency}" . PHP_EOL;
        if ($this->verbose) {
            echo $style->dim('  Verbose      ') . 'on' . PHP_EOL;
        }
    }

    public function onResult(Result $result, int $current, int $total): void
    {
        $style = $this->styleOrThrow();
        $groupName = $result->case->group;
        $sessionId = $result->case->sessionId;

        if ($this->currentGroup !== $groupName) {
            $this->closeSessionIfOpen();
            $this->currentGroup = $groupName;
            $this->printGroupHeader($groupName);
        }

        if ($sessionId !== null && $this->currentSession !== $sessionId) {
            $this->closeSessionIfOpen();
            $this->currentSession = $sessionId;
            $this->printSessionHeader($sessionId);
        } elseif ($sessionId === null && $this->currentSession !== null) {
            $this->closeSessionIfOpen();
        }

        $counter = $style->dim("[{$current}/{$total}]");
        $label = $result->case->describe();
        $duration = DurationFormatter::seconds($result->response->durationSeconds);
        $indent = $sessionId !== null ? '    ' : '  ';

        if ($result->isSkipped()) {
            echo "  {$counter} " . $style->yellow('⊘') . "{$indent}{$label}  " . $style->dim($duration) . PHP_EOL;
            echo '       ' . str_repeat(' ', mb_strlen($indent)) . $style->dim($result->skipReason ?? 'Skipped') . PHP_EOL;
            return;
        }

        $retrySuffix = $this->retrySuffix($result);
        $http = $style->dim("HTTP {$result->response->statusCode}");

        if ($result->isPassed()) {
            echo "  {$counter} " . $style->green('✔') . "{$indent}{$label}  " . $style->dim($duration) . "  {$http}{$retrySuffix}" . PHP_EOL;
            if ($this->verbose) {
                $this->printVerbose($result);
            }
            return;
        }

        echo "  {$counter} " . $style->red('✖') . "{$indent}" . $style->bold($label) . '  ' . $style->dim($duration) . "  {$http}{$retrySuffix}" . PHP_EOL;
        foreach ($result->failures as $failure) {
            echo '       ' . str_repeat(' ', mb_strlen($indent)) . $style->red("↳ {$failure}") . PHP_EOL;
        }
        if ($this->verbose) {
            $this->printVerbose($result);
        }
    }

    public function onEnd(Report $report): void
    {
        $this->closeSessionIfOpen();

        $style = $this->styleOrThrow();
        $duration = DurationFormatter::seconds($report->getTotalDuration());
        $total = $report->getTotalCount();
        $passed = $report->getPassedCount();
        $failed = $report->getFailedCount();
        $skipped = $report->getSkippedCount();

        echo PHP_EOL;
        echo $style->dim('  ' . str_repeat('═', 54)) . PHP_EOL;

        if ($report->isSuccessful()) {
            echo '  ' . $style->green('✔') . '  ' . $style->bold("All {$total} smoke tests passed") . $style->dim("  ({$duration})") . PHP_EOL;
        } else {
            $parts = [];
            if ($passed > 0) {
                $parts[] = $style->green("{$passed} passed");
            }
            if ($failed > 0) {
                $parts[] = $style->red("{$failed} failed");
            }
            if ($skipped > 0) {
                $parts[] = $style->yellow("{$skipped} skipped");
            }
            echo '  ' . $style->red('✖') . '  ' . $style->bold('Smoke tests: ') . implode($style->dim(', '), $parts) . $style->dim("  ({$duration})") . PHP_EOL;
        }
        echo PHP_EOL;
    }

    private function styleOrThrow(): AnsiStyle
    {
        if ($this->style === null) {
            throw new \LogicException('AnsiStyle not initialized.');
        }

        return $this->style;
    }

    private function printGroupHeader(string $groupName): void
    {
        $style = $this->styleOrThrow();
        $count = $this->groupSizes[$groupName] ?? 0;
        echo PHP_EOL;
        echo '  ' . $style->bold($style->cyan("▸ {$groupName}"));
        if ($count > 0) {
            echo $style->dim("  ({$count} tests)");
        }
        echo PHP_EOL;
        echo $style->dim('  ' . str_repeat('─', 54)) . PHP_EOL;
    }

    private function printSessionHeader(string $sessionId): void
    {
        $style = $this->styleOrThrow();
        $label = self::extractSessionLabel($sessionId);
        echo '    ' . $style->dim('🔒 ') . $style->bold($style->magenta($label)) . $style->dim('  (shared cookies)') . PHP_EOL;
    }

    private function closeSessionIfOpen(): void
    {
        if ($this->currentSession === null) {
            return;
        }
        echo '    ' . $this->styleOrThrow()->dim('🔓 session end') . PHP_EOL;
        $this->currentSession = null;
    }

    private function retrySuffix(Result $result): string
    {
        if ($result->attempts <= 1) {
            return '';
        }
        $totalMs = (int) round($result->totalDurationSeconds * 1000);
        $retries = $result->attempts - 1;

        return '  ' . $this->styleOrThrow()->dim("· retried {$retries}× in {$totalMs}ms");
    }

    private function printVerbose(Result $result): void
    {
        $style = $this->styleOrThrow();
        $pad = '       ';
        $case = $result->case;
        echo $pad . $style->dim(str_repeat('·', 50)) . PHP_EOL;
        echo $pad . $style->bold($style->blue('REQUEST')) . PHP_EOL;
        echo $pad . $style->bold($case->method->value) . '  ' . $style->cyan($case->url) . PHP_EOL;

        foreach ($case->headers as $name => $value) {
            echo $pad . $style->dim("{$name}: ") . self::maskHeader($name, $value) . PHP_EOL;
        }
        if ($case->body !== null) {
            echo $pad . $style->dim('Body:') . PHP_EOL;
            if ($case->sendAsJson && is_array($case->body)) {
                $encoded = json_encode($case->body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $bodyStr = $encoded === false ? '' : $encoded;
            } elseif (is_array($case->body)) {
                $bodyStr = http_build_query($case->body);
            } else {
                $bodyStr = $case->body;
            }
            foreach (explode(PHP_EOL, $bodyStr) as $line) {
                echo $pad . '  ' . $style->dim($line) . PHP_EOL;
            }
        }

        echo $pad . $style->bold($style->blue('RESPONSE')) . PHP_EOL;
        if ($result->response->statusCode === 0) {
            echo $pad . $style->red('(no response — connection error)') . PHP_EOL;
        } else {
            echo $pad . $style->bold($this->statusColor($result->response->statusCode)) . PHP_EOL;
            foreach ($result->response->headers as $name => $value) {
                echo $pad . $style->dim("{$name}: {$value}") . PHP_EOL;
            }
            if ($result->response->body !== '') {
                echo $pad . $style->dim('Body:') . PHP_EOL;
                $body = $this->formatBody($result->response->body);
                foreach (explode(PHP_EOL, $body) as $line) {
                    echo $pad . '  ' . $line . PHP_EOL;
                }
            }
        }
        echo $pad . $style->dim(str_repeat('·', 50)) . PHP_EOL . PHP_EOL;
    }

    private function statusColor(int $code): string
    {
        $style = $this->styleOrThrow();
        $text = "HTTP {$code}";

        return match (true) {
            $code >= 500 => $style->red($text),
            $code >= 400 => $style->yellow($text),
            $code >= 300 => $style->cyan($text),
            default => $style->green($text),
        };
    }

    private function formatBody(string $body): string
    {
        $trimmed = trim($body);
        if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($pretty !== false) {
                    return mb_strlen($pretty) > self::VERBOSE_BODY_MAX
                        ? mb_substr($pretty, 0, self::VERBOSE_BODY_MAX) . PHP_EOL . '… [truncated, ' . mb_strlen($pretty) . ' chars]'
                        : $pretty;
                }
            }
        }

        return mb_strlen($trimmed) > self::VERBOSE_BODY_MAX
            ? mb_substr($trimmed, 0, self::VERBOSE_BODY_MAX) . PHP_EOL . '… [truncated, ' . mb_strlen($trimmed) . ' chars]'
            : $trimmed;
    }

    private static function maskHeader(string $name, string $value): string
    {
        if (strtolower($name) !== 'authorization') {
            return $value;
        }
        $parts = explode(' ', $value, 2);
        if (count($parts) === 2) {
            $token = $parts[1];
            $tail = mb_strlen($token) > 4 ? mb_substr($token, -4) : $token;

            return $parts[0] . ' ' . str_repeat('*', max(0, mb_strlen($token) - 4)) . $tail;
        }
        $tail = mb_strlen($value) > 4 ? mb_substr($value, -4) : $value;

        return str_repeat('*', max(0, mb_strlen($value) - 4)) . $tail;
    }

    private static function extractSessionLabel(string $sessionId): string
    {
        if (preg_match('/^sess_[a-f0-9]+_(.+)$/', $sessionId, $m) === 1) {
            return str_replace('_', ' ', $m[1]);
        }

        return 'Session';
    }
}

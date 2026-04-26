<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Execution;

use Closure;
use Stromcom\HttpSmoke\Capture\CaptureStore;
use Stromcom\HttpSmoke\Definition\GroupConfig;
use Stromcom\HttpSmoke\Definition\TestCase;
use Stromcom\HttpSmoke\Exception\VariableNotFoundException;
use Stromcom\HttpSmoke\Http\HttpClientInterface;
use Stromcom\HttpSmoke\Http\Response;
use Stromcom\HttpSmoke\Reporting\ReporterInterface;

final class Runner
{
    /** @var list<ReporterInterface> */
    private array $reporters = [];

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly CaseTranslator $translator,
        private readonly CaptureStore $captures,
    ) {}

    public function addReporter(ReporterInterface $reporter): self
    {
        $this->reporters[] = $reporter;

        return $this;
    }

    /**
     * @param array<string, GroupConfig>     $groups
     * @param array<string, list<TestCase>>  $casesByGroup
     */
    public function run(array $groups, array $casesByGroup, ?Closure $onResult = null): Report
    {
        $report = new Report();
        $report->setGroups($groups);

        $totalTests = 0;
        foreach ($casesByGroup as $cases) {
            $totalTests += count($cases);
        }

        foreach ($this->reporters as $reporter) {
            $reporter->onStart($groups, $totalTests);
        }

        $start = microtime(true);
        $current = 0;

        foreach ($casesByGroup as $groupName => $cases) {
            $config = $groups[$groupName] ?? new GroupConfig($groupName);
            $failures = 0;
            $circuitOpen = false;

            foreach ($this->segment($cases) as $segment) {
                if ($circuitOpen) {
                    foreach ($segment['cases'] as $case) {
                        $result = Result::skipped(
                            $case,
                            "Circuit breaker: group \"{$groupName}\" exceeded {$config->maxFailures} failures",
                        );
                        $current = $this->emit($report, $result, $current, $totalTests, $onResult);
                    }

                    continue;
                }

                $segmentResults = $segment['type'] === 'session'
                    ? $this->runSessionChain($segment['cases'])
                    : $this->runParallel($segment['cases']);

                foreach ($segmentResults as $result) {
                    if ($result->isFailed()) {
                        $failures++;
                    }
                    $current = $this->emit($report, $result, $current, $totalTests, $onResult);
                }

                if ($failures >= $config->maxFailures) {
                    $circuitOpen = true;
                }
            }
        }

        $report->setTotalDuration(microtime(true) - $start);

        foreach ($this->reporters as $reporter) {
            $reporter->onEnd($report);
        }

        return $report;
    }

    /**
     * @param list<TestCase> $cases
     * @return list<array{type: 'parallel'|'session', cases: list<TestCase>}>
     */
    private function segment(array $cases): array
    {
        $segments = [];
        $regular = [];
        $session = [];
        $sessionId = null;

        foreach ($cases as $case) {
            if ($case->sessionId === null) {
                if ($sessionId !== null) {
                    $segments[] = ['type' => 'session', 'cases' => $session];
                    $session = [];
                    $sessionId = null;
                }
                $regular[] = $case;
                continue;
            }

            if ($sessionId !== null && $case->sessionId !== $sessionId) {
                $segments[] = ['type' => 'session', 'cases' => $session];
                $session = [];
            }
            if ($regular !== []) {
                $segments[] = ['type' => 'parallel', 'cases' => $regular];
                $regular = [];
            }
            $sessionId = $case->sessionId;
            $session[] = $case;
        }

        if ($regular !== []) {
            $segments[] = ['type' => 'parallel', 'cases' => $regular];
        }
        if ($session !== []) {
            $segments[] = ['type' => 'session', 'cases' => $session];
        }

        return $segments;
    }

    /**
     * @param list<TestCase> $cases
     * @return list<Result>
     */
    private function runParallel(array $cases): array
    {
        $results = [];
        $requests = [];
        $caseIndex = [];

        foreach ($cases as $i => $case) {
            try {
                $requests[] = $this->translator->toRequest($case);
                $caseIndex[] = $i;
            } catch (VariableNotFoundException $e) {
                $results[$i] = Result::from(
                    $case,
                    Response::transportFailure($e->getMessage(), 0.0),
                    [$e->getMessage()],
                );
            }
        }

        if ($requests !== []) {
            $start = microtime(true);
            $responses = $this->client->sendMany($requests);

            foreach ($responses as $idx => $response) {
                $caseIdx = $caseIndex[$idx];
                $case = $cases[$caseIdx];
                $result = $this->buildResult($case, $response);
                $results[$caseIdx] = $this->retryNonSession($case, $result);
            }

            $chunkDuration = microtime(true) - $start;
            foreach ($results as $result) {
                if ($result->totalDurationSeconds < $chunkDuration) {
                    $result->setRetryMetadata($result->attempts, $chunkDuration);
                }
            }
        }

        ksort($results);
        $ordered = array_values($results);

        foreach ($ordered as $result) {
            if (!$result->isFailed()) {
                $this->processCaptures($result);
            }
        }

        return $ordered;
    }

    /**
     * @param list<TestCase> $cases
     * @return list<Result>
     */
    private function runSessionChain(array $cases): array
    {
        $results = [];
        $cookieJar = tempnam(sys_get_temp_dir(), 'smoke_cookies_');
        if ($cookieJar === false) {
            $cookieJar = sys_get_temp_dir() . '/smoke_cookies_' . bin2hex(random_bytes(8));
        }

        $broken = false;
        foreach ($cases as $case) {
            if ($broken) {
                $results[] = Result::skipped($case, 'Session chain: previous request failed — skipping remaining');
                continue;
            }

            $start = microtime(true);
            $attempts = 1;

            try {
                $request = $this->translator->toRequest($case, $cookieJar);
            } catch (VariableNotFoundException $e) {
                $result = Result::from(
                    $case,
                    Response::transportFailure($e->getMessage(), 0.0),
                    [$e->getMessage()],
                );
                $result->setRetryMetadata($attempts, microtime(true) - $start);
                $results[] = $result;
                $broken = true;
                continue;
            }

            $response = $this->client->send($request);
            $result = $this->buildResult($case, $response);

            while ($result->isFailed() && $case->retryOnFailure > 0 && $attempts <= $case->retryOnFailure) {
                usleep(max(1, $case->retryDelayMs) * 1000);
                $request = $this->translator->toRequest($case, $cookieJar);
                $response = $this->client->send($request);
                $result = $this->buildResult($case, $response);
                $attempts++;
            }

            if ($result->isFailed() && $case->retryOnFailure === 0 && $case->retryOn5xx > 0 && $response->statusCode >= 500) {
                usleep(500_000);
                $request = $this->translator->toRequest($case, $cookieJar);
                $response = $this->client->send($request);
                $result = $this->buildResult($case, $response);
                $attempts++;
            }

            $result->setRetryMetadata($attempts, microtime(true) - $start);
            $results[] = $result;

            if ($result->isFailed()) {
                $broken = true;
            } else {
                $this->processCaptures($result);
            }
        }

        if (is_file($cookieJar)) {
            @unlink($cookieJar);
        }

        return $results;
    }

    /**
     * @param list<string> $failures
     */
    private function buildResult(TestCase $case, Response $response, array $failures = []): Result
    {
        if ($response->isTransportError()) {
            return Result::from($case, $response, ["cURL error: {$response->transportError}"]);
        }

        $found = $failures;
        foreach ($this->translator->resolveAssertions($case) as $assertion) {
            $message = $assertion->evaluate($response);
            if ($message !== null) {
                $found[] = $message;
            }
        }

        return Result::from($case, $response, $found);
    }

    private function retryNonSession(TestCase $case, Result $result): Result
    {
        if (!$result->isFailed()) {
            return $result;
        }

        $attempts = 1;
        if ($case->retryOnFailure > 0) {
            while ($result->isFailed() && $attempts <= $case->retryOnFailure) {
                usleep(max(1, $case->retryDelayMs) * 1000);
                try {
                    $request = $this->translator->toRequest($case);
                } catch (VariableNotFoundException $e) {
                    return Result::from(
                        $case,
                        Response::transportFailure($e->getMessage(), 0.0),
                        [$e->getMessage()],
                    );
                }
                $response = $this->client->send($request);
                $result = $this->buildResult($case, $response);
                $attempts++;
            }
            $result->setRetryMetadata($attempts, $result->totalDurationSeconds);

            return $result;
        }

        if ($case->retryOn5xx > 0 && $result->response->statusCode >= 500) {
            usleep(500_000);
            try {
                $request = $this->translator->toRequest($case);
                $response = $this->client->send($request);
                $result = $this->buildResult($case, $response);
                $result->setRetryMetadata(2, $result->totalDurationSeconds);
            } catch (VariableNotFoundException $e) {
                return Result::from(
                    $case,
                    Response::transportFailure($e->getMessage(), 0.0),
                    [$e->getMessage()],
                );
            }
        }

        return $result;
    }

    private function processCaptures(Result $result): void
    {
        foreach ($result->case->captures as $capture) {
            $value = $capture->extract($result->response);
            if ($value !== null) {
                $this->captures->set($capture->name(), $value);
            }
        }
    }

    private function emit(Report $report, Result $result, int $current, int $total, ?Closure $onResult): int
    {
        $report->addResult($result);
        $current++;

        foreach ($this->reporters as $reporter) {
            $reporter->onResult($result, $current, $total);
        }

        if ($onResult !== null) {
            $onResult($result, $current, $total);
        }

        return $current;
    }
}

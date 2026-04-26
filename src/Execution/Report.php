<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Execution;

use Stromcom\HttpSmoke\Definition\GroupConfig;

final class Report
{
    /** @var list<Result> */
    private array $results = [];

    /** @var array<string, GroupConfig> */
    private array $groups = [];

    private float $totalDurationSeconds = 0.0;

    public function addResult(Result $result): void
    {
        $this->results[] = $result;
    }

    /**
     * @param array<string, GroupConfig> $groups
     */
    public function setGroups(array $groups): void
    {
        $this->groups = $groups;
    }

    public function setTotalDuration(float $duration): void
    {
        $this->totalDurationSeconds = $duration;
    }

    public function getTotalDuration(): float
    {
        return $this->totalDurationSeconds;
    }

    /**
     * @return list<Result>
     */
    public function getAllResults(): array
    {
        return $this->results;
    }

    /**
     * @return array<string, list<Result>>
     */
    public function getResultsByGroup(): array
    {
        $byGroup = [];
        foreach ($this->results as $result) {
            $byGroup[$result->case->group][] = $result;
        }

        return $byGroup;
    }

    /**
     * @return array<string, GroupConfig>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    public function getTotalCount(): int
    {
        return count($this->results);
    }

    public function getPassedCount(): int
    {
        return count(array_filter($this->results, static fn(Result $r): bool => $r->isPassed()));
    }

    public function getFailedCount(): int
    {
        return count(array_filter($this->results, static fn(Result $r): bool => $r->isFailed()));
    }

    public function getSkippedCount(): int
    {
        return count(array_filter($this->results, static fn(Result $r): bool => $r->isSkipped()));
    }

    public function isSuccessful(): bool
    {
        foreach ($this->results as $result) {
            if ($result->isFailed()) {
                return false;
            }
        }

        return true;
    }
}

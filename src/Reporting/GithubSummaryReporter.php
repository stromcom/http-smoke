<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Reporting;

use Stromcom\HttpSmoke\Execution\Report;
use Stromcom\HttpSmoke\Execution\Result;

final class GithubSummaryReporter implements ReporterInterface
{
    private readonly MarkdownReporter $markdown;

    private readonly JsonReporter $json;

    public function __construct(?string $environment = null)
    {
        $this->json = new JsonReporter(environment: $environment);
        $this->markdown = new MarkdownReporter(json: $this->json, environment: $environment);
    }

    public function onStart(array $groups, int $totalTests): void {}

    public function onResult(Result $result, int $current, int $total): void {}

    public function onEnd(Report $report): void
    {
        $summaryFile = getenv('GITHUB_STEP_SUMMARY');
        if (!is_string($summaryFile) || $summaryFile === '') {
            return;
        }

        $data = $this->json->generate($report);
        $markdown = $this->markdown->build($data);
        @file_put_contents($summaryFile, $markdown, FILE_APPEND);
    }
}

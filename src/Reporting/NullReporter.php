<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Reporting;

use Stromcom\HttpSmoke\Execution\Report;
use Stromcom\HttpSmoke\Execution\Result;

final class NullReporter implements ReporterInterface
{
    public function onStart(array $groups, int $totalTests): void {}

    public function onResult(Result $result, int $current, int $total): void {}

    public function onEnd(Report $report): void {}
}

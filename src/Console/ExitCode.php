<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Console;

enum ExitCode: int
{
    case Success = 0;
    case TestsFailed = 1;
    case ConfigError = 2;
    case UsageError = 3;
}

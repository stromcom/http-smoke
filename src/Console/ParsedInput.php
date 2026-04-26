<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Console;

final class ParsedInput
{
    public ?string $environment = null;

    public bool $showHelp = false;

    public bool $verbose = false;

    public bool $insecureTls = false;

    public bool $noConsole = false;

    public bool $noGithubSummary = false;

    public ?int $concurrency = null;

    public ?string $jsonOutputPath = null;

    public ?string $markdownOutputPath = null;

    public ?string $configDir = null;

    public ?string $configFile = null;

    public ?string $smokeJsonPath = null;

    public ?string $envFilePath = null;

    public ?string $filter = null;

    public ?string $groupFilter = null;

    /** @var array<string, string> */
    public array $cliVariables = [];
}

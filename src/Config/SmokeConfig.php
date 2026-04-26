<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Config;

use Closure;
use Stromcom\HttpSmoke\Container\Container;
use Stromcom\HttpSmoke\Reporting\ReporterInterface;
use Stromcom\HttpSmoke\Variable\VariableSourceInterface;

final class SmokeConfig
{
    public string $configDir = 'tests/SmokeHttp';

    public ?string $smokeJsonPath = null;

    public ?string $envFilePath = null;

    public string $environment = 'dev';

    public int $concurrency = 10;

    public bool $insecureTls = false;

    public bool $verbose = false;

    public ?string $filter = null;

    public ?string $groupFilter = null;

    public ?string $jsonOutputPath = null;

    public ?string $markdownOutputPath = null;

    public bool $githubSummary = true;

    public bool $consoleReporter = true;

    public string $userAgent = 'StromcomSmokeTest/1.0';

    /** @var array<string, string> */
    public array $cliVariables = [];

    /** @var list<VariableSourceInterface> */
    public array $extraVariableSources = [];

    /** @var list<ReporterInterface> */
    public array $extraReporters = [];

    /** @var Closure(Container): void|null */
    public ?Closure $configureContainer = null;

    /**
     * @return list<string>  Environment aliases used to look up matching keys in smokeHttp.json
     */
    public function environmentAliases(): array
    {
        $map = [
            'prod' => ['prod', 'production'],
            'production' => ['prod', 'production'],
            'staging' => ['staging'],
            'dev' => ['dev', 'development', 'local'],
            'development' => ['dev', 'development', 'local'],
            'local' => ['dev', 'development', 'local'],
            'testing' => ['testing'],
        ];

        $aliases = $map[$this->environment] ?? [$this->environment];
        if (!in_array($this->environment, $aliases, true)) {
            $aliases = [$this->environment, ...$aliases];
        }

        return array_values(array_unique($aliases));
    }
}

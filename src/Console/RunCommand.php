<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Console;

use Stromcom\HttpSmoke\Config\SmokeConfig;
use Stromcom\HttpSmoke\Config\SmokeConfigLoader;
use Stromcom\HttpSmoke\Container\ServiceFactory;
use Stromcom\HttpSmoke\Definition\GroupConfig;
use Stromcom\HttpSmoke\Definition\Suite;
use Stromcom\HttpSmoke\Definition\TestCase;
use Stromcom\HttpSmoke\Discovery\ConfigDiscovery;
use Stromcom\HttpSmoke\Exception\ConfigException;
use Stromcom\HttpSmoke\Exception\SmokeException;
use Stromcom\HttpSmoke\Execution\Runner;

final class RunCommand
{
    public function __construct(
        private readonly InputParser $parser = new InputParser(),
        private readonly SmokeConfigLoader $configLoader = new SmokeConfigLoader(),
    ) {}

    /**
     * @param list<string> $argv
     */
    public function execute(array $argv, string $projectRoot): ExitCode
    {
        $argv = array_slice($argv, 1);
        $input = $this->parser->parse($argv);

        if ($input->showHelp) {
            fwrite(STDOUT, $this->parser->helpText());

            return ExitCode::Success;
        }

        if ($input->environment === null) {
            fwrite(STDERR, $this->parser->helpText());

            return ExitCode::UsageError;
        }

        try {
            $config = $this->buildConfig($input, $projectRoot);
            $container = ServiceFactory::build($config);

            $suite = $container->getTyped(Suite::class);
            $discovery = $container->getTyped(ConfigDiscovery::class);

            $files = $discovery->discover($config->configDir, $config->filter);
            if ($files === []) {
                fwrite(STDERR, "No *.php config files found in: {$config->configDir}\n");

                return ExitCode::ConfigError;
            }
            foreach ($files as $file) {
                $discovery->load($suite, $file);
            }

            [$groups, $casesByGroup] = $this->filterByGroup($suite, $config->groupFilter);
            $totalTests = array_sum(array_map('count', $casesByGroup));
            if ($totalTests === 0) {
                fwrite(STDERR, "No smoke tests defined in: {$config->configDir}\n");

                return ExitCode::ConfigError;
            }

            $runner = $container->getTyped(Runner::class);
            $report = $runner->run($groups, $casesByGroup);

            return $report->isSuccessful() ? ExitCode::Success : ExitCode::TestsFailed;
        } catch (SmokeException $e) {
            fwrite(STDERR, "Error: {$e->getMessage()}\n");

            return ExitCode::ConfigError;
        }
    }

    private function buildConfig(ParsedInput $input, string $projectRoot): SmokeConfig
    {
        $config = new SmokeConfig();
        $config->environment = $input->environment ?? 'dev';

        $configFile = $input->configFile ?? $projectRoot . '/smoke.config.php';
        $this->configLoader->load($config, $configFile);

        if ($input->configDir !== null) {
            $config->configDir = $input->configDir;
        } elseif (!str_starts_with($config->configDir, '/') && preg_match('#^[A-Za-z]:[\\\\/]#', $config->configDir) !== 1) {
            $config->configDir = $projectRoot . '/' . $config->configDir;
        }

        $config->smokeJsonPath = $input->smokeJsonPath ?? $config->smokeJsonPath ?? $projectRoot . '/tests/smokeHttp.json';
        $config->envFilePath = $input->envFilePath ?? $config->envFilePath ?? $projectRoot . '/.env.' . $this->mapEnvFile($config->environment);

        if ($input->concurrency !== null) {
            $config->concurrency = max(1, $input->concurrency);
        }
        if ($input->verbose) {
            $config->verbose = true;
        }
        if ($input->insecureTls) {
            $config->insecureTls = true;
        }
        if ($input->jsonOutputPath !== null) {
            $config->jsonOutputPath = $this->resolvePath($input->jsonOutputPath);
        }
        if ($input->markdownOutputPath !== null) {
            $config->markdownOutputPath = $this->resolvePath($input->markdownOutputPath);
        }
        if ($input->filter !== null) {
            $config->filter = $input->filter;
        }
        if ($input->groupFilter !== null) {
            $config->groupFilter = $input->groupFilter;
        }
        if ($input->noConsole) {
            $config->consoleReporter = false;
        }
        if ($input->noGithubSummary) {
            $config->githubSummary = false;
        }
        foreach ($input->cliVariables as $key => $value) {
            $config->cliVariables[$key] = $value;
        }

        return $config;
    }

    private function mapEnvFile(string $environment): string
    {
        return match ($environment) {
            'prod', 'production' => 'production',
            'staging' => 'staging',
            'dev', 'development', 'local' => 'development',
            'testing' => 'testing',
            default => $environment,
        };
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return $path;
        }
        $cwd = getcwd();

        return ($cwd === false ? '.' : $cwd) . '/' . $path;
    }

    /**
     * @return array{0: array<string, GroupConfig>, 1: array<string, list<TestCase>>}
     */
    private function filterByGroup(Suite $suite, ?string $filter): array
    {
        $groups = $suite->getGroups();
        $casesByGroup = $suite->getCasesByGroup();

        if ($filter === null) {
            return [$groups, $casesByGroup];
        }

        $isWildcard = str_contains($filter, '*');
        $matched = array_filter(
            array_keys($casesByGroup),
            static fn(string $name): bool => $isWildcard
                ? fnmatch($filter, $name, FNM_CASEFOLD)
                : $name === $filter,
        );

        if ($matched === []) {
            $available = implode(', ', array_keys($casesByGroup));
            throw new ConfigException("Group \"{$filter}\" not found.\nAvailable groups: {$available}");
        }

        $flip = array_flip($matched);

        return [array_intersect_key($groups, $flip), array_intersect_key($casesByGroup, $flip)];
    }
}

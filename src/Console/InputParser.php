<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Console;

final class InputParser
{
    /**
     * @param list<string> $argv  Already shifted past the script name.
     */
    public function parse(array $argv): ParsedInput
    {
        $input = new ParsedInput();

        foreach ($argv as $arg) {
            if ($arg === '--help' || $arg === '-h') {
                $input->showHelp = true;
            } elseif ($arg === '--verbose' || $arg === '-v') {
                $input->verbose = true;
            } elseif ($arg === '--insecure' || $arg === '-k') {
                $input->insecureTls = true;
            } elseif ($arg === '--no-console') {
                $input->noConsole = true;
            } elseif ($arg === '--no-github-summary') {
                $input->noGithubSummary = true;
            } elseif (str_starts_with($arg, '--concurrency=')) {
                $input->concurrency = (int) substr($arg, 14);
            } elseif (str_starts_with($arg, '--output-json=')) {
                $input->jsonOutputPath = substr($arg, 14);
            } elseif (str_starts_with($arg, '--output=')) {
                $input->markdownOutputPath = substr($arg, 9);
            } elseif (str_starts_with($arg, '--base-url=')) {
                $input->cliVariables['APP_BASE_URL'] = substr($arg, 11);
            } elseif (str_starts_with($arg, '--config-dir=')) {
                $input->configDir = substr($arg, 13);
            } elseif (str_starts_with($arg, '--config=')) {
                $input->configFile = substr($arg, 9);
            } elseif (str_starts_with($arg, '--smoke-json=')) {
                $input->smokeJsonPath = substr($arg, 13);
            } elseif (str_starts_with($arg, '--env-file=')) {
                $input->envFilePath = substr($arg, 11);
            } elseif (str_starts_with($arg, '--filter=')) {
                $input->filter = substr($arg, 9);
            } elseif (str_starts_with($arg, '--group=')) {
                $input->groupFilter = substr($arg, 8);
            } elseif (str_starts_with($arg, '--var=')) {
                $value = substr($arg, 6);
                $eq = strpos($value, '=');
                if ($eq !== false) {
                    $input->cliVariables[substr($value, 0, $eq)] = substr($value, $eq + 1);
                }
            } elseif (!str_starts_with($arg, '-')) {
                $input->environment = $arg;
            }
        }

        return $input;
    }

    public function helpText(string $binary = 'http-smoke'): string
    {
        return <<<HELP

              HTTP Smoke Test Runner
              ──────────────────────

              Usage:
                {$binary} <environment> [options]

              Options:
                --concurrency=N         Max parallel requests (default: 10)
                --config-dir=DIR        Test definitions directory (default: tests/SmokeHttp)
                --config=FILE           Path to smoke.config.php (default: ./smoke.config.php)
                --smoke-json=FILE       Path to smokeHttp.json (default: ./tests/smokeHttp.json)
                --env-file=FILE         Path to .env.<env> file (default: ./.env.<environment>)
                --base-url=URL          Override APP_BASE_URL variable
                --var=KEY=VALUE         Set/override an arbitrary variable (repeatable)
                --filter=PATTERN        Run only files whose name matches *PATTERN*
                --group=NAME            Run only the specified group (supports wildcards: api.*)
                --output=FILE           Write Markdown report to file
                --output-json=FILE      Write canonical JSON report to file
                --no-console            Suppress console reporter
                --no-github-summary     Skip writing to GITHUB_STEP_SUMMARY
                --verbose, -v           Show full request/response details
                --insecure, -k          Disable TLS peer verification
                --help, -h              Show this help


            HELP;
    }
}

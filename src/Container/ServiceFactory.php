<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Container;

use Stromcom\HttpSmoke\Capture\CaptureStore;
use Stromcom\HttpSmoke\Config\SmokeConfig;
use Stromcom\HttpSmoke\Definition\Suite;
use Stromcom\HttpSmoke\Discovery\ConfigDiscovery;
use Stromcom\HttpSmoke\Execution\CaseTranslator;
use Stromcom\HttpSmoke\Execution\Runner;
use Stromcom\HttpSmoke\Http\Curl\CurlMultiClient;
use Stromcom\HttpSmoke\Http\HttpClientInterface;
use Stromcom\HttpSmoke\Reporting\ConsoleReporter;
use Stromcom\HttpSmoke\Reporting\GithubSummaryReporter;
use Stromcom\HttpSmoke\Reporting\JsonReporter;
use Stromcom\HttpSmoke\Reporting\MarkdownReporter;
use Stromcom\HttpSmoke\Variable\Source\ArraySource;
use Stromcom\HttpSmoke\Variable\Source\EnvFileSource;
use Stromcom\HttpSmoke\Variable\Source\GetenvSource;
use Stromcom\HttpSmoke\Variable\Source\JsonFileSource;
use Stromcom\HttpSmoke\Variable\VariableResolver;

final class ServiceFactory
{
    public static function build(SmokeConfig $config): Container
    {
        $container = new Container();
        $container->setInstance(SmokeConfig::class, $config);

        $container->set(VariableResolver::class, static function () use ($config): VariableResolver {
            $resolver = new VariableResolver();

            if ($config->envFilePath !== null && is_file($config->envFilePath)) {
                $resolver->addSource(new EnvFileSource($config->envFilePath));
            }
            if ($config->smokeJsonPath !== null && is_file($config->smokeJsonPath)) {
                $resolver->addSource(new JsonFileSource($config->smokeJsonPath, $config->environmentAliases()));
            }
            $resolver->addSource(new GetenvSource());
            foreach ($config->extraVariableSources as $source) {
                $resolver->addSource($source);
            }
            if ($config->cliVariables !== []) {
                $resolver->addSource(new ArraySource($config->cliVariables));
            }

            return $resolver;
        });

        $container->set(CaptureStore::class, static fn(): CaptureStore => new CaptureStore());

        $container->set(HttpClientInterface::class, static fn(): HttpClientInterface => new CurlMultiClient($config->concurrency));

        $container->set(CaseTranslator::class, static fn(Container $c): CaseTranslator => new CaseTranslator(
            $c->getTyped(VariableResolver::class),
            $c->getTyped(CaptureStore::class),
            $config->insecureTls,
            $config->userAgent,
        ));

        $container->set(Suite::class, static fn(Container $c): Suite => new Suite(
            $c->getTyped(VariableResolver::class),
        ));

        $container->set(ConfigDiscovery::class, static fn(): ConfigDiscovery => new ConfigDiscovery());

        $container->set(Runner::class, static function (Container $c) use ($config): Runner {
            $runner = new Runner(
                $c->getTyped(HttpClientInterface::class),
                $c->getTyped(CaseTranslator::class),
                $c->getTyped(CaptureStore::class),
            );

            if ($config->consoleReporter) {
                $runner->addReporter(new ConsoleReporter(
                    verbose: $config->verbose,
                    environment: $config->environment,
                    concurrency: $config->concurrency,
                ));
            }
            if ($config->jsonOutputPath !== null) {
                $runner->addReporter(new JsonReporter(
                    outputPath: $config->jsonOutputPath,
                    environment: $config->environment,
                    concurrency: $config->concurrency,
                ));
            }
            if ($config->markdownOutputPath !== null) {
                $runner->addReporter(new MarkdownReporter(
                    outputPath: $config->markdownOutputPath,
                    environment: $config->environment,
                ));
            }
            if ($config->githubSummary) {
                $runner->addReporter(new GithubSummaryReporter(environment: $config->environment));
            }
            foreach ($config->extraReporters as $reporter) {
                $runner->addReporter($reporter);
            }

            return $runner;
        });

        if ($config->configureContainer !== null) {
            ($config->configureContainer)($container);
        }

        return $container;
    }
}

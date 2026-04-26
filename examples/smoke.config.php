<?php

declare(strict_types=1);

use Stromcom\HttpSmoke\Config\SmokeConfig;
use Stromcom\HttpSmoke\Container\Container;
use Stromcom\HttpSmoke\Variable\Source\ArraySource;

return static function (SmokeConfig $config): void {
    $config->configDir = __DIR__ . '/SmokeHttp';
    $config->smokeJsonPath = __DIR__ . '/smokeHttp.json';
    $config->concurrency = 10;
    $config->verbose = false;

    $config->extraVariableSources[] = new ArraySource([
        'CUSTOM_HEADER' => 'X-Smoke-Run',
    ]);

    $config->configureContainer = static function (Container $container): void {
        // Override services here if needed, e.g. swap HttpClientInterface with your own implementation:
        // $container->set(HttpClientInterface::class, fn() => new MyClient());
    };
};

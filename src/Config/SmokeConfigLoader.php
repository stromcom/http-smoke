<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Config;

use Stromcom\HttpSmoke\Exception\ConfigException;

final class SmokeConfigLoader
{
    public function load(SmokeConfig $config, ?string $configFilePath): SmokeConfig
    {
        if ($configFilePath === null || !is_file($configFilePath)) {
            return $config;
        }

        $callback = require $configFilePath;
        if (!is_callable($callback)) {
            throw new ConfigException("smoke.config.php must return a callable taking SmokeConfig: {$configFilePath}");
        }

        $callback($config);

        return $config;
    }
}

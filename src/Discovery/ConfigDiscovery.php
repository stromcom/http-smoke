<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Discovery;

use DirectoryIterator;
use Stromcom\HttpSmoke\Definition\Suite;
use Stromcom\HttpSmoke\Exception\ConfigException;

final class ConfigDiscovery
{
    /**
     * @return list<string>
     */
    public function discover(string $directory, ?string $filter = null): array
    {
        if (!is_dir($directory)) {
            throw new ConfigException("Config directory not found: {$directory}");
        }

        $files = $this->collect($directory);

        if ($filter !== null) {
            $needle = '*' . $filter . '*';
            $files = array_values(array_filter(
                $files,
                static fn(string $f): bool => fnmatch($needle, basename($f), FNM_CASEFOLD),
            ));
        }

        return $files;
    }

    public function load(Suite $suite, string $file): void
    {
        $closure = require $file;
        if (!is_callable($closure)) {
            throw new ConfigException("Config file must return a callable: {$file}");
        }
        $closure($suite);
    }

    /**
     * @return list<string>
     */
    private function collect(string $directory): array
    {
        $files = [];
        $subdirs = [];

        foreach (new DirectoryIterator($directory) as $entry) {
            if ($entry->isDot()) {
                continue;
            }
            if ($entry->isFile() && strtolower($entry->getExtension()) === 'php') {
                $files[] = $entry->getPathname();
            } elseif ($entry->isDir()) {
                $subdirs[] = $entry->getPathname();
            }
        }

        sort($files);
        sort($subdirs);

        $result = $files;
        foreach ($subdirs as $sub) {
            $result = [...$result, ...$this->collect($sub)];
        }

        return $result;
    }
}

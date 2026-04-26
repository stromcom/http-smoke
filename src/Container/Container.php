<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Container;

use Closure;
use Psr\Container\ContainerInterface;
use Stromcom\HttpSmoke\Container\Exception\NotFoundException;

final class Container implements ContainerInterface
{
    /** @var array<string, Closure(self): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /**
     * @template T of object
     * @param class-string<T>|string $id
     * @param Closure(self): T $factory
     */
    public function set(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function setInstance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
        unset($this->factories[$id]);
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || array_key_exists($id, $this->instances);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }
        if (!isset($this->factories[$id])) {
            throw new NotFoundException("Service not found: {$id}");
        }
        $this->instances[$id] = ($this->factories[$id])($this);

        return $this->instances[$id];
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function getTyped(string $id): object
    {
        $value = $this->get($id);
        if (!$value instanceof $id) {
            throw new NotFoundException("Service \"{$id}\" did not produce an instance of {$id}.");
        }

        return $value;
    }
}

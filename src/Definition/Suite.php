<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Definition;

use Stromcom\HttpSmoke\Exception\VariableNotFoundException;
use Stromcom\HttpSmoke\Variable\VariableResolver;

final class Suite
{
    /** @var array<string, GroupConfig> */
    private array $groups = [];

    /** @var list<GroupBuilder> */
    private array $builders = [];

    /** @var array<string, string> */
    private array $defaultHeaders = [];

    private bool $defaultAsJson = false;

    public function __construct(private readonly ?VariableResolver $variables = null) {}

    /**
     * Read a variable from the merged variable sources (.env, smokeHttp.json,
     * OS env, CLI overrides). Returns null when the variable is not defined or
     * when no resolver is wired (e.g. unit-test construction).
     *
     * Use this when you need the value at config-build time — for example to
     * include it in a JSON request body, where `{KEY}` placeholder substitution
     * does not run.
     */
    public function variable(string $name): ?string
    {
        return $this->variables?->get($name);
    }

    /**
     * Same as {@see variable()}, but throws {@see VariableNotFoundException}
     * when the variable is missing. Use when the test cannot run meaningfully
     * without the value.
     */
    public function variableOrFail(string $name): string
    {
        $value = $this->variable($name);
        if ($value === null) {
            throw VariableNotFoundException::for($name);
        }

        return $value;
    }

    public function header(string $name, ?string $value): self
    {
        if ($value === null) {
            unset($this->defaultHeaders[$name]);
        } else {
            $this->defaultHeaders[$name] = $value;
        }

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getDefaultHeaders(): array
    {
        return $this->defaultHeaders;
    }

    public function asJson(bool $value = true): self
    {
        $this->defaultAsJson = $value;

        return $this;
    }

    public function getDefaultAsJson(): bool
    {
        return $this->defaultAsJson;
    }

    public function group(string $name, int $maxFailures = 3): GroupBuilder
    {
        if (!isset($this->groups[$name])) {
            $this->groups[$name] = new GroupConfig($name, $maxFailures);
        }

        $builder = new GroupBuilder($name, $this);
        $this->builders[] = $builder;

        return $builder;
    }

    /**
     * @return array<string, GroupConfig>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * @return array<string, list<TestCase>>
     */
    public function getCasesByGroup(): array
    {
        $byGroup = [];
        foreach ($this->builders as $builder) {
            $name = $builder->getGroupName();
            $byGroup[$name] = [...($byGroup[$name] ?? []), ...$builder->finalize()];
        }

        return $byGroup;
    }

    public function getTotalCount(): int
    {
        $count = 0;
        foreach ($this->getCasesByGroup() as $cases) {
            $count += count($cases);
        }

        return $count;
    }
}

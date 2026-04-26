<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Variable\Source;

use Stromcom\HttpSmoke\Variable\VariableSourceInterface;

final class GetenvSource implements VariableSourceInterface
{
    /**
     * @param list<string> $allowList  When non-empty, only variables present in this list can be returned (whitelist).
     */
    public function __construct(
        private readonly array $allowList = [],
    ) {}

    public function get(string $name): ?string
    {
        if ($this->allowList !== [] && !in_array($name, $this->allowList, true)) {
            return null;
        }

        $value = getenv($name);
        if ($value === false || $value === '') {
            return null;
        }

        return $value;
    }

    public function all(): array
    {
        $vars = [];
        foreach ($this->allowList as $name) {
            $value = $this->get($name);
            if ($value !== null) {
                $vars[$name] = $value;
            }
        }

        return $vars;
    }
}

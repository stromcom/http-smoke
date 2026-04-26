<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Variable;

interface VariableSourceInterface
{
    public function get(string $name): ?string;

    /**
     * @return array<string, string>
     */
    public function all(): array;
}

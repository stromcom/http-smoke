<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Assertion;

use Stromcom\HttpSmoke\Variable\VariableResolver;

interface ResolvableAssertion extends AssertionInterface
{
    /**
     * Returns a copy of this assertion with all variable placeholders
     * resolved against the given variable resolver.
     */
    public function withResolver(VariableResolver $variables): self;
}

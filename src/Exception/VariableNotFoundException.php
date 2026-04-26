<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Exception;

final class VariableNotFoundException extends SmokeException
{
    public static function for(string $name): self
    {
        return new self("Unresolved variable: {{$name}}. Check your variable sources (.env, smokeHttp.json, OS env, CLI overrides).");
    }
}

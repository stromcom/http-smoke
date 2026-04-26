<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Capture;

use Stromcom\HttpSmoke\Http\Response;

interface CaptureInterface
{
    public function name(): string;

    public function extract(Response $response): ?string;
}

<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Capture;

use Stromcom\HttpSmoke\Http\Response;

final readonly class HeaderCapture implements CaptureInterface
{
    public function __construct(
        private string $name,
        private string $headerName,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function extract(Response $response): ?string
    {
        return $response->getHeader($this->headerName);
    }
}

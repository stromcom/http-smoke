<?php

declare(strict_types=1);

namespace Stromcom\HttpSmoke\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;
use Stromcom\HttpSmoke\Exception\SmokeException;

final class NotFoundException extends SmokeException implements NotFoundExceptionInterface {}

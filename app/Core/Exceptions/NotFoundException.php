<?php

declare(strict_types=1);

namespace BarePitch\Core\Exceptions;

class NotFoundException extends \RuntimeException
{
    public function __construct(string $message = 'Not found', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

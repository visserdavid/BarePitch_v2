<?php

declare(strict_types=1);

namespace BarePitch\Core\Exceptions;

class AuthorizationException extends \RuntimeException
{
    public function __construct(string $message = 'Forbidden', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

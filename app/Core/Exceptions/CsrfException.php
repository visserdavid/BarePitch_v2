<?php

declare(strict_types=1);

namespace BarePitch\Core\Exceptions;

class CsrfException extends \RuntimeException
{
    public function __construct(string $message = 'CSRF token mismatch', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

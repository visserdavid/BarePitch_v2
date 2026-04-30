<?php

declare(strict_types=1);

namespace BarePitch\Core\Exceptions;

class ValidationException extends \RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(
        private readonly array $errors,
        string $message = 'Validation failed',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /** @return array<string, string> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

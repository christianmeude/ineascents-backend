<?php

namespace App\Exceptions;

use Exception;

class VerificationCodeException extends Exception
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly array $meta = [],
    ) {
        parent::__construct($message);
    }

    public function toResponse(): array
    {
        return array_merge([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
        ], $this->meta);
    }
}

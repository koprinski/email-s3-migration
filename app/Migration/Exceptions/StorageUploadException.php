<?php

namespace App\Migration\Exceptions;

use Throwable;

class StorageUploadException extends MigrationException
{
    public function __construct(string $message, private readonly bool $retryable = true, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public static function transient(string $message, ?Throwable $previous = null): self
    {
        return new self($message, true, $previous);
    }

    public static function permanent(string $message, ?Throwable $previous = null): self
    {
        return new self($message, false, $previous);
    }
}

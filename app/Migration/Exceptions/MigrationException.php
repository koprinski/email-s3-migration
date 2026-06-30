<?php

namespace App\Migration\Exceptions;

use RuntimeException;

class MigrationException extends RuntimeException
{
    public function isRetryable(): bool
    {
        return true;
    }
}

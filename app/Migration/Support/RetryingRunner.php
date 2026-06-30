<?php

namespace App\Migration\Support;

use App\Migration\Exceptions\MigrationException;
use Illuminate\Support\Sleep;
use Throwable;

final class RetryingRunner
{
    public function __construct(
        private readonly int $tries,
        private readonly int $baseDelayMs,
        private readonly int $maxDelayMs,
    ) {}

    public function run(callable $operation, ?callable $onRetry = null): mixed
    {
        $delay = $this->baseDelayMs;

        for ($attempt = 1; ; $attempt++) {
            try {
                return $operation();
            } catch (Throwable $e) {
                $retryable = ! ($e instanceof MigrationException) || $e->isRetryable();

                if (! $retryable || $attempt >= $this->tries) {
                    throw $e;
                }

                if ($onRetry !== null) {
                    $onRetry($e, $attempt);
                }

                $delay = min($this->maxDelayMs, random_int($this->baseDelayMs, max($this->baseDelayMs, $delay * 3)));
                Sleep::for($delay)->milliseconds();
            }
        }
    }
}

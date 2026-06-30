<?php

namespace App\Migration\Contracts;

use App\Migration\Enums\MigrationPhase;
use Throwable;

interface FailureRecorderInterface
{
    public function record(string $runId, int $emailId, MigrationPhase $phase, Throwable $e, int $attempts = 1): void;

    /** distinct email ids with a recorded failure */
    public function streamFailedEmailIds(int $chunk): iterable;

    public function countFailedEmails(): int;
}

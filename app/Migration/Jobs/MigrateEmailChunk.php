<?php

namespace App\Migration\Jobs;

use App\Migration\Contracts\FailureRecorderInterface;
use App\Migration\Enums\MigrationPhase;
use App\Migration\Services\EmailMigrationOrchestrator;
use DateTime;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class MigrateEmailChunk implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly array $ids,
        public readonly string $runId,
    ) {}

    public function handle(EmailMigrationOrchestrator $orchestrator): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $summary = $orchestrator->run($this->runId, $this->ids);

        Log::info('migration chunk processed', ['run_id' => $this->runId] + $summary->toArray());
    }

    /** Wall-clock ceiling so a chunk can't be retried forever. */
    public function retryUntil(): DateTime
    {
        return now()->addHours(6);
    }

    /** Terminal (post-retry) chunk failure — record every id so --retry-failed can find them. */
    public function failed(Throwable $e): void
    {
        $recorder = app(FailureRecorderInterface::class);

        foreach ($this->ids as $id) {
            $recorder->record($this->runId, $id, MigrationPhase::Migrate, $e, $this->tries);
        }
    }

    public function tags(): array
    {
        return ['emails-s3', "run:{$this->runId}"];
    }
}

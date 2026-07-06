<?php

namespace App\Console\Commands;

use App\Migration\Contracts\EmailRepositoryInterface;
use App\Migration\Contracts\FailureRecorderInterface;
use App\Migration\Jobs\MigrateEmailChunk;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Throwable;

class MigrateEmailsToS3Command extends Command
{
    protected $signature = 'emails:migrate-to-s3
        {--chunk= : Emails per chunk/job (default: config migration.chunk_size)}
        {--limit= : Process at most N emails}
        {--from-id= : Only process emails with id greater than this}
        {--retry-failed : Reprocess only emails recorded in migration_failures}
        {--force : Dispatch even if a previous migration batch is still running}';

    protected $description = 'Migrate email bodies and attachments to S3 (idempotent, resumable).';

    public function handle(EmailRepositoryInterface $emails, FailureRecorderInterface $failures): int
    {
        // TTL must outlive the slowest dispatch (streaming millions of ids), not just the common case.
        $lock = Cache::lock('emails:migrate-to-s3:dispatch', 3600);

        if (! $lock->get()) {
            $this->components->error('Another emails:migrate-to-s3 invocation is already dispatching. Aborting.');

            return self::FAILURE;
        }

        try {
            return $this->dispatchMigration($emails, $failures);
        } finally {
            $lock->release();
        }
    }

    private function dispatchMigration(EmailRepositoryInterface $emails, FailureRecorderInterface $failures): int
    {
        if (! $this->option('force') && ($active = $this->activeBatchName()) !== null) {
            $this->components->error(
                "Migration batch '{$active}' still has jobs in flight; dispatching again would double the work. "
                .'Wait for it to finish or pass --force.'
            );

            return self::FAILURE;
        }

        $chunkSize = (int) ($this->option('chunk') ?: config('migration.chunk_size'));
        $fromId = $this->option('from-id') !== null ? (int) $this->option('from-id') : null;
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $retryFailed = (bool) $this->option('retry-failed');
        $runId = strtolower((string) Str::ulid());

        [$ids, $total] = $retryFailed
            ? [$failures->streamFailedEmailIds($chunkSize), $failures->countFailedEmails()]
            : [
                $emails->streamUnmigratedIds($chunkSize, $fromId, $limit),
                $emails->countUnmigrated($fromId, $limit),
            ];

        $this->components->info(sprintf(
            'Run %s | %s | %d email(s)',
            $runId,
            $retryFailed ? 'retry-failed' : 'migrate',
            $total,
        ));

        if ($total === 0) {
            $this->components->info('Nothing to do.');

            return self::SUCCESS;
        }

        $queue = (string) config('migration.queue');

        $jobs = LazyCollection::make(function () use ($ids) {
            yield from $ids;
        })
            ->chunk($chunkSize)
            ->map(fn (LazyCollection $chunk) => new MigrateEmailChunk($chunk->values()->all(), $runId))
            ->all();

        $batch = Bus::batch($jobs)
            ->name("emails-s3:{$runId}")
            ->allowFailures()
            ->onQueue($queue)
            ->then(fn (Batch $b) => Log::info('migration batch finished', ['run_id' => $runId, 'batch' => $b->id]))
            ->catch(fn (Batch $b, Throwable $e) => Log::error('migration batch error', ['run_id' => $runId, 'batch' => $b->id, 'message' => $e->getMessage()]))
            ->finally(fn (Batch $b) => Log::info('migration batch closed', ['run_id' => $runId, 'batch' => $b->id, 'failed_jobs' => $b->failedJobs]))
            ->dispatch();

        $this->components->info(sprintf("Dispatched batch %s: %d job(s) (~%d emails) on queue '%s'.", $batch->id, count($jobs), $total, $queue));
        $this->line("  Workers:   php artisan queue:work --queue={$queue}");
        $this->line('  Note: exit 0 means dispatched, not migrated. Failures land in migration_failures.');

        return self::SUCCESS;
    }

    /**
     * Name of a previous run's batch that still has jobs in flight.
     * Rows where every remaining pending job has terminally failed don't count.
     */
    private function activeBatchName(): ?string
    {
        return DB::table('job_batches')
            ->where('name', 'like', 'emails-s3:%')
            ->whereNull('finished_at')
            ->whereNull('cancelled_at')
            ->whereColumn('pending_jobs', '>', 'failed_jobs')
            ->orderByDesc('created_at')
            ->value('name');
    }
}

<?php

namespace Tests\Feature;

use App\Migration\Contracts\FailureRecorderInterface;
use App\Migration\Enums\MigrationPhase;
use App\Migration\Jobs\MigrateEmailChunk;
use App\Models\Email;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class MigrateEmailsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_a_batch_of_chunk_jobs_for_unmigrated_emails(): void
    {
        Bus::fake();
        Email::factory()->count(5)->create();

        $this->artisan('emails:migrate-to-s3')->assertSuccessful();

        Bus::assertBatched(function (PendingBatch $batch) {
            $job = $batch->jobs->first();

            return $batch->jobs->count() === 1
                && $job instanceof MigrateEmailChunk
                && count($job->ids) === 5;
        });
    }

    public function test_chunk_option_controls_job_count(): void
    {
        Bus::fake();
        Email::factory()->count(5)->create();

        $this->artisan('emails:migrate-to-s3 --chunk=2')->assertSuccessful();

        Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->count() === 3);
    }

    public function test_retry_failed_dispatches_only_failed_ids(): void
    {
        Bus::fake();
        $failed = Email::factory()->create();
        Email::factory()->create();

        $this->app->make(FailureRecorderInterface::class)
            ->record('seed', $failed->id, MigrationPhase::Migrate, new RuntimeException('x'));

        $this->artisan('emails:migrate-to-s3 --retry-failed')->assertSuccessful();

        Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->first()->ids === [$failed->id]);
    }

    public function test_nothing_to_do_dispatches_no_batch(): void
    {
        Bus::fake();

        $this->artisan('emails:migrate-to-s3')->assertSuccessful();

        Bus::assertNothingBatched();
    }

    public function test_refuses_while_a_previous_batch_still_has_jobs_in_flight(): void
    {
        Bus::fake();
        Email::factory()->create();
        $this->insertBatch(pendingJobs: 3, failedJobs: 0);

        $this->artisan('emails:migrate-to-s3')->assertFailed();

        Bus::assertNothingBatched();
    }

    public function test_force_overrides_the_active_batch_guard(): void
    {
        Bus::fake();
        Email::factory()->create();
        $this->insertBatch(pendingJobs: 3, failedJobs: 0);

        $this->artisan('emails:migrate-to-s3 --force')->assertSuccessful();

        Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->count() === 1);
    }

    public function test_a_batch_whose_remaining_jobs_all_failed_does_not_block_a_new_run(): void
    {
        Bus::fake();
        Email::factory()->create();
        $this->insertBatch(pendingJobs: 2, failedJobs: 2);

        $this->artisan('emails:migrate-to-s3')->assertSuccessful();

        Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->count() === 1);
    }

    public function test_refuses_when_another_invocation_holds_the_dispatch_lock(): void
    {
        // The database lock driver can't take two competing locks inside the
        // test transaction (Postgres aborts it), so exercise this via the array store.
        config(['cache.default' => 'array']);

        Bus::fake();
        Email::factory()->create();
        Cache::lock('emails:migrate-to-s3:dispatch', 300)->get();

        $this->artisan('emails:migrate-to-s3')->assertFailed();

        Bus::assertNothingBatched();
    }

    private function insertBatch(int $pendingJobs, int $failedJobs): void
    {
        DB::table('job_batches')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'emails-s3:previous',
            'total_jobs' => 5,
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
            'failed_job_ids' => '[]',
            'options' => null,
            'cancelled_at' => null,
            'created_at' => now()->getTimestamp(),
            'finished_at' => null,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Migration\Contracts\FailureRecorderInterface;
use App\Migration\Enums\MigrationPhase;
use App\Migration\Jobs\MigrateEmailChunk;
use App\Models\Email;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
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
}

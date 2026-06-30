<?php

namespace Tests\Feature;

use App\Migration\Enums\MigrationPhase;
use App\Migration\Jobs\MigrateEmailChunk;
use App\Migration\Services\EmailMigrationOrchestrator;
use App\Models\Email;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class MigrateEmailChunkTest extends TestCase
{
    use RefreshDatabase;

    public function test_processes_chunk_and_is_idempotent(): void
    {
        Storage::fake('s3');
        Storage::fake('local');
        Email::factory()->count(5)->withFiles(2)->create();
        $ids = Email::pluck('id')->all();

        $this->process($ids);

        $this->assertSame(5, Email::whereNotNull('migrated_at')->count());
        $email = Email::first();
        Storage::disk('s3')->assertExists($email->body_s3_path);
        $this->assertNotNull($email->body);

        $before = count(Storage::disk('s3')->allFiles());
        $this->process($ids);
        $this->assertSame(5, Email::whereNotNull('migrated_at')->count());
        $this->assertSame($before, count(Storage::disk('s3')->allFiles()));
    }

    public function test_missing_attachment_is_skipped_and_email_still_migrates(): void
    {
        Storage::fake('s3');
        Storage::fake('local');
        $email = Email::factory()->create(['file_ids' => [999]]);

        $this->process([$email->id]);

        $this->assertNotNull($email->fresh()->migrated_at);
        $this->assertSame([], $email->fresh()->file_s3_paths);
    }

    public function test_failed_records_every_id_to_the_dead_letter_table(): void
    {
        (new MigrateEmailChunk([11, 22], 'run-fail'))->failed(new RuntimeException('worker crashed'));

        $this->assertDatabaseCount('migration_failures', 2);
        $this->assertDatabaseHas('migration_failures', [
            'email_id' => 11,
            'phase' => MigrationPhase::Migrate->value,
            'attempts' => 3,
        ]);
        $this->assertDatabaseHas('migration_failures', ['email_id' => 22]);
    }

    public function test_metadata_is_run_scoped(): void
    {
        $job = new MigrateEmailChunk([1, 2], 'run-tags');

        $this->assertContains('run:run-tags', $job->tags());
        $this->assertTrue($job->retryUntil() > now());
    }

    private function process(array $ids): void
    {
        (new MigrateEmailChunk($ids, 'run-test'))
            ->handle($this->app->make(EmailMigrationOrchestrator::class));
    }
}

<?php

namespace Tests\Feature;

use App\Migration\Contracts\EmailRepositoryInterface;
use App\Migration\Contracts\ObjectStorage;
use App\Migration\Enums\MigrationPhase;
use App\Migration\Exceptions\EmailPersistException;
use App\Migration\Exceptions\StorageUploadException;
use App\Migration\Repositories\EloquentEmailRepository;
use App\Migration\Services\EmailMigrationOrchestrator;
use App\Models\Email;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class EmailMigrationOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_an_upload_failure_and_leaves_the_row_pending(): void
    {
        Storage::fake('local');
        $this->app->bind(ObjectStorage::class, fn () => new class implements ObjectStorage
        {
            public function put(string $key, string $contents): void
            {
                throw StorageUploadException::transient('s3 unreachable');
            }

            public function putStream(string $key, $stream, int $expectedBytes): void {}

            public function exists(string $key): bool
            {
                return false;
            }

            public function size(string $key): int
            {
                return 0;
            }

            public function delete(string $key): void {}
        });

        $email = Email::factory()->create(['file_ids' => []]);

        $summary = $this->app->make(EmailMigrationOrchestrator::class)->run('run-fail', [$email->id]);

        $this->assertSame(1, $summary->failed);
        $this->assertSame(0, $summary->migrated);
        $this->assertNull($email->fresh()->migrated_at);
        $this->assertDatabaseHas('migration_failures', [
            'email_id' => $email->id,
            'phase' => MigrationPhase::Migrate->value,
            'exception_class' => StorageUploadException::class,
        ]);
    }

    public function test_records_a_persist_failure_under_the_persist_phase(): void
    {
        Storage::fake('s3');
        Storage::fake('local');
        $email = Email::factory()->create(['file_ids' => []]);

        $this->app->bind(EmailRepositoryInterface::class, fn () => new class(new EloquentEmailRepository) implements EmailRepositoryInterface
        {
            public function __construct(private EloquentEmailRepository $inner) {}

            public function streamUnmigratedIds(int $chunk, ?int $fromId = null, ?int $limit = null): iterable
            {
                return $this->inner->streamUnmigratedIds($chunk, $fromId, $limit);
            }

            public function countUnmigrated(?int $fromId = null, ?int $limit = null): int
            {
                return $this->inner->countUnmigrated($fromId, $limit);
            }

            public function findForMigration(int $id): ?Email
            {
                return $this->inner->findForMigration($id);
            }

            public function findManyUnmigrated(array $ids): Collection
            {
                return $this->inner->findManyUnmigrated($ids);
            }

            public function markMigrated(int $id, ?string $bodyS3Path, array $fileS3Paths): bool
            {
                throw new RuntimeException('database has gone away');
            }

            public function streamMigratedUnverifiedIds(int $chunk): iterable
            {
                return $this->inner->streamMigratedUnverifiedIds($chunk);
            }

            public function countMigratedUnverified(): int
            {
                return $this->inner->countMigratedUnverified();
            }

            public function markVerified(int $id): bool
            {
                return $this->inner->markVerified($id);
            }
        });

        $summary = $this->app->make(EmailMigrationOrchestrator::class)->run('run-persist', [$email->id]);

        $this->assertSame(1, $summary->failed);
        $this->assertDatabaseHas('migration_failures', [
            'email_id' => $email->id,
            'phase' => MigrationPhase::Persist->value,
            'exception_class' => EmailPersistException::class,
        ]);
    }
}

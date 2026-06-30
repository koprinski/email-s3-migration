<?php

namespace Tests\Feature;

use App\Migration\Repositories\EloquentEmailRepository;
use App\Models\Email;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_migrated_is_compare_and_set_and_preserves_file_ids(): void
    {
        $email = Email::factory()->create(['file_ids' => [1, 2], 'migrated_at' => null]);
        $repo = new EloquentEmailRepository;

        $won = $repo->markMigrated($email->id, "emails/{$email->id}/body.html", ['1' => 'k1', '2' => 'k2']);
        $this->assertTrue($won);

        $email->refresh();
        $this->assertNotNull($email->migrated_at);
        $this->assertSame("emails/{$email->id}/body.html", $email->body_s3_path);
        $this->assertSame(['1' => 'k1', '2' => 'k2'], $email->file_s3_paths);
        $this->assertSame([1, 2], $email->file_ids);

        $this->assertFalse($repo->markMigrated($email->id, 'other', []));
    }

    public function test_stream_unmigrated_ids_skips_migrated_rows(): void
    {
        Email::factory()->count(3)->create(['migrated_at' => null]);
        Email::factory()->migrated()->create();

        $repo = new EloquentEmailRepository;
        $ids = iterator_to_array($repo->streamUnmigratedIds(2));

        $this->assertCount(3, $ids);
        $this->assertSame(3, $repo->countUnmigrated());
    }

    public function test_stream_unmigrated_ids_honours_from_id_and_limit(): void
    {
        $ids = Email::factory()->count(5)->create(['migrated_at' => null])
            ->pluck('id')->sort()->values();
        $repo = new EloquentEmailRepository;

        $fromSecond = array_values(iterator_to_array($repo->streamUnmigratedIds(10, $ids[1])));
        $this->assertSame($ids->slice(2)->values()->all(), $fromSecond);

        $this->assertCount(2, iterator_to_array($repo->streamUnmigratedIds(10, null, 2)));
        $this->assertSame(3, $repo->countUnmigrated($ids[1]));
        $this->assertSame(2, $repo->countUnmigrated(null, 2));
    }

    public function test_find_many_unmigrated_returns_only_pending_rows(): void
    {
        $pending = Email::factory()->count(2)->create(['migrated_at' => null]);
        $migrated = Email::factory()->migrated()->create();
        $repo = new EloquentEmailRepository;

        $found = $repo->findManyUnmigrated([...$pending->pluck('id')->all(), $migrated->id]);

        $this->assertCount(2, $found);
        $this->assertFalse($found->has($migrated->id));
        $this->assertTrue($repo->findManyUnmigrated([])->isEmpty());
    }

    public function test_stream_migrated_unverified_ids_paginates(): void
    {
        Email::factory()->count(5)->migrated()->create();
        $repo = new EloquentEmailRepository;

        $this->assertCount(5, iterator_to_array($repo->streamMigratedUnverifiedIds(2)));
        $this->assertSame(5, $repo->countMigratedUnverified());
    }

    public function test_mark_verified_requires_migrated(): void
    {
        $email = Email::factory()->migrated()->create();
        $repo = new EloquentEmailRepository;

        $this->assertTrue($repo->markVerified($email->id));
        $this->assertFalse($repo->markVerified($email->id));
    }
}

<?php

namespace Tests\Unit\Migration;

use App\Migration\Contracts\ObjectStorage;
use App\Migration\DTO\EmailData;
use App\Migration\DTO\FileData;
use App\Migration\Strategies\AttachmentMigrationStrategy;
use App\Migration\Strategies\BodyMigrationStrategy;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class StrategiesTest extends TestCase
{
    public function test_body_strategy_uploads_to_deterministic_key(): void
    {
        Storage::fake('s3');
        $result = (new BodyMigrationStrategy($this->app->make(ObjectStorage::class)))
            ->migrate(new EmailData(5, '<b>x</b>', [], []));

        $this->assertSame('emails/5/body.html', $result->bodyS3Path);
        Storage::disk('s3')->assertExists('emails/5/body.html');
    }

    public function test_attachment_strategy_uploads_and_builds_keyed_map(): void
    {
        Storage::fake('s3');
        Storage::fake('local');
        Storage::disk('local')->put('attachments/a.bin', 'hello');

        $email = new EmailData(3, null, [7], [new FileData(7, 'a.bin', 'attachments/a.bin', 5, 'application/octet-stream')]);
        $result = (new AttachmentMigrationStrategy($this->app->make(ObjectStorage::class), Storage::disk('local')))
            ->migrate($email);

        $this->assertSame(['7' => 'emails/3/attachments/7_a.bin'], $result->fileS3Paths);
        Storage::disk('s3')->assertExists('emails/3/attachments/7_a.bin');
    }

    public function test_attachment_missing_file_is_skipped(): void
    {
        Storage::fake('s3');
        Storage::fake('local');

        $email = new EmailData(3, null, [7], [new FileData(7, 'a.bin', 'attachments/missing.bin', 5, 'x')]);
        $result = (new AttachmentMigrationStrategy($this->app->make(ObjectStorage::class), Storage::disk('local')))
            ->migrate($email);

        $this->assertSame([], $result->fileS3Paths);
        $this->assertCount(1, $result->skipped);
    }

    public function test_attachment_without_a_files_row_is_skipped(): void
    {
        Storage::fake('s3');
        Storage::fake('local');

        $email = new EmailData(3, null, [7], []);
        $result = (new AttachmentMigrationStrategy($this->app->make(ObjectStorage::class), Storage::disk('local')))
            ->migrate($email);

        $this->assertSame([], $result->fileS3Paths);
        $this->assertSame('files row not found', $result->skipped[0]['reason']);
    }

    public function test_attachment_with_an_unreadable_source_is_skipped(): void
    {
        Storage::fake('s3');

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('exists')->andReturn(true);
        $disk->shouldReceive('size')->andReturn(5);
        $disk->shouldReceive('readStream')->andReturn(false);

        $email = new EmailData(3, null, [7], [new FileData(7, 'a.bin', 'p', 5, 'x')]);
        $result = (new AttachmentMigrationStrategy($this->app->make(ObjectStorage::class), $disk))
            ->migrate($email);

        $this->assertSame([], $result->fileS3Paths);
        $this->assertSame('source file unreadable', $result->skipped[0]['reason']);
    }
}

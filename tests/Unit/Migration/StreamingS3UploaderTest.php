<?php

namespace Tests\Unit\Migration;

use App\Migration\Exceptions\StorageUploadException;
use App\Migration\Services\StreamingS3Uploader;
use App\Migration\Support\RetryingRunner;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Mockery;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Tests\TestCase;

class StreamingS3UploaderTest extends TestCase
{
    private function uploader(): StreamingS3Uploader
    {
        return new StreamingS3Uploader(Storage::disk('s3'), new RetryingRunner(1, 1, 1), new NullLogger);
    }

    public function test_put_uploads_and_verifies(): void
    {
        Storage::fake('s3');
        $this->uploader()->put('emails/1/body.html', '<html>hi</html>');

        Storage::disk('s3')->assertExists('emails/1/body.html');
        $this->assertSame('<html>hi</html>', Storage::disk('s3')->get('emails/1/body.html'));
    }

    public function test_put_stream_uploads(): void
    {
        Storage::fake('s3');
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'binary-bytes');
        rewind($stream);

        $this->uploader()->putStream('emails/1/attachments/1_a.bin', $stream, strlen('binary-bytes'));
        fclose($stream);

        Storage::disk('s3')->assertExists('emails/1/attachments/1_a.bin');
    }

    public function test_size_mismatch_throws(): void
    {
        Storage::fake('s3');
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'abc');
        rewind($stream);

        $this->expectException(StorageUploadException::class);
        $this->uploader()->putStream('k', $stream, 999);
    }

    public function test_exists_size_and_delete_delegate_to_the_disk(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('emails/1/body.html', 'hello');

        $uploader = $this->uploader();

        $this->assertTrue($uploader->exists('emails/1/body.html'));
        $this->assertSame(5, $uploader->size('emails/1/body.html'));

        $uploader->delete('emails/1/body.html');
        $this->assertFalse($uploader->exists('emails/1/body.html'));
    }

    public function test_retries_and_logs_a_transient_failure_then_succeeds(): void
    {
        Sleep::fake();

        $attempts = 0;
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->twice()->andReturnUsing(function () use (&$attempts) {
            if (++$attempts === 1) {
                throw new RuntimeException('network blip');
            }

            return true;
        });
        $disk->shouldReceive('size')->andReturn(3);

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('warning')->once();

        (new StreamingS3Uploader($disk, new RetryingRunner(3, 1, 1), $logger))->put('k', 'abc');

        $this->assertSame(2, $attempts);
    }

    public function test_a_false_write_result_is_treated_as_a_failure(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->andReturn(false);

        $this->expectException(StorageUploadException::class);

        (new StreamingS3Uploader($disk, new RetryingRunner(1, 1, 1), new NullLogger))->put('k', 'abc');
    }

    public function test_a_migration_exception_is_not_double_wrapped(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('put')->once()->andThrow(StorageUploadException::permanent('access denied'));

        try {
            (new StreamingS3Uploader($disk, new RetryingRunner(3, 1, 1), new NullLogger))->put('k', 'abc');
            $this->fail('expected StorageUploadException');
        } catch (StorageUploadException $e) {
            $this->assertFalse($e->isRetryable());
        }
    }
}

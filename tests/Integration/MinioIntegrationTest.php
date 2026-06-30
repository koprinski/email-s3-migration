<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MinioIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (env('RUN_MINIO_TESTS') !== '1') {
            $this->markTestSkipped('Set RUN_MINIO_TESTS=1 (with MinIO up) to run the integration suite.');
        }
    }

    public function test_large_stream_round_trips_with_correct_size(): void
    {
        $disk = Storage::disk(config('migration.s3_disk'));
        $key = 'integration/'.Str::ulid().'.bin';
        $size = 6 * 1024 * 1024;

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, str_repeat('x', $size));
        rewind($stream);

        $disk->writeStream($key, $stream);
        fclose($stream);

        $this->assertTrue($disk->exists($key));
        $this->assertSame($size, $disk->size($key));

        $disk->delete($key);
    }
}

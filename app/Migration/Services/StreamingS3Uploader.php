<?php

namespace App\Migration\Services;

use App\Migration\Contracts\ObjectStorage;
use App\Migration\Exceptions\MigrationException;
use App\Migration\Exceptions\StorageUploadException;
use App\Migration\Support\RetryingRunner;
use Illuminate\Contracts\Filesystem\Filesystem;
use Psr\Log\LoggerInterface;
use Throwable;

final class StreamingS3Uploader implements ObjectStorage
{
    public function __construct(
        private readonly Filesystem $disk,
        private readonly RetryingRunner $runner,
        private readonly LoggerInterface $logger,
    ) {}

    public function put(string $key, string $contents): void
    {
        $expected = strlen($contents);

        $this->runner->run(
            fn () => $this->attempt($key, $expected, fn () => $this->disk->put($key, $contents)),
            fn (Throwable $e, int $attempt) => $this->logRetry($key, $e, $attempt),
        );
    }

    public function putStream(string $key, $stream, int $expectedBytes): void
    {
        $this->runner->run(
            function () use ($key, $stream, $expectedBytes) {
                if (is_resource($stream) && stream_get_meta_data($stream)['seekable']) {
                    rewind($stream);
                }

                return $this->attempt($key, $expectedBytes, fn () => $this->disk->writeStream($key, $stream));
            },
            fn (Throwable $e, int $attempt) => $this->logRetry($key, $e, $attempt),
        );
    }

    public function exists(string $key): bool
    {
        return $this->disk->exists($key);
    }

    public function size(string $key): int
    {
        return (int) $this->disk->size($key);
    }

    public function delete(string $key): void
    {
        $this->disk->delete($key);
    }

    /**
     * Run one write attempt and verify it.
     */
    private function attempt(string $key, int $expectedBytes, callable $write): void
    {
        try {
            $ok = $write();
        } catch (MigrationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw StorageUploadException::transient("Upload failed for {$key}: {$e->getMessage()}", $e);
        }

        if ($ok === false) {
            throw StorageUploadException::transient("Upload returned false for {$key}");
        }

        $actual = $this->disk->size($key);
        if ($actual === false || (int) $actual !== $expectedBytes) {
            throw StorageUploadException::transient(
                "Size mismatch for {$key}: expected {$expectedBytes}, got ".var_export($actual, true)
            );
        }
    }

    private function logRetry(string $key, Throwable $e, int $attempt): void
    {
        $this->logger->warning('s3 upload retry', [
            'key' => $key,
            'attempt' => $attempt,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }
}

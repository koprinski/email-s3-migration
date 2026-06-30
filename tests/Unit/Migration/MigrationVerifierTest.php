<?php

namespace Tests\Unit\Migration;

use App\Migration\Contracts\FileRepositoryInterface;
use App\Migration\Contracts\ObjectStorage;
use App\Migration\DTO\FileData;
use App\Migration\Services\MigrationVerifier;
use App\Models\Email;
use Tests\TestCase;

class MigrationVerifierTest extends TestCase
{
    public function test_passes_when_body_and_attachments_match(): void
    {
        $email = new Email([
            'body' => 'hi',
            'body_s3_path' => 'emails/1/body.html',
            'file_ids' => [7],
            'file_s3_paths' => ['7' => 'emails/1/attachments/7_a.pdf'],
        ]);

        $verifier = new MigrationVerifier(
            $this->storage(['emails/1/body.html' => 2, 'emails/1/attachments/7_a.pdf' => 3]),
            $this->fileRepo([new FileData(7, 'a.pdf', 'p', 3, 'application/pdf')]),
        );

        $this->assertSame([], $verifier->verify($email));
    }

    public function test_flags_body_problems(): void
    {
        $files = $this->fileRepo([]);

        $missingPath = new Email(['body' => 'abc', 'body_s3_path' => null, 'file_ids' => [], 'file_s3_paths' => []]);
        $this->assertSame(
            ['missing body_s3_path'],
            (new MigrationVerifier($this->storage([]), $files))->verify($missingPath),
        );

        $missingObject = new Email(['body' => 'abc', 'body_s3_path' => 'emails/1/body.html', 'file_ids' => [], 'file_s3_paths' => []]);
        $this->assertSame(
            ['body object missing'],
            (new MigrationVerifier($this->storage([]), $files))->verify($missingObject),
        );

        $sizeMismatch = new Email(['body' => 'abc', 'body_s3_path' => 'emails/1/body.html', 'file_ids' => [], 'file_s3_paths' => []]);
        $this->assertSame(
            ['body size mismatch'],
            (new MigrationVerifier($this->storage(['emails/1/body.html' => 999]), $files))->verify($sizeMismatch),
        );
    }

    public function test_flags_attachment_problems(): void
    {
        $files = $this->fileRepo([new FileData(7, 'a.pdf', 'p', 3, 'application/pdf')]);

        $missingObject = new Email([
            'body' => 'hi',
            'body_s3_path' => 'b',
            'file_ids' => [7],
            'file_s3_paths' => ['7' => 'k7'],
        ]);
        $this->assertSame(
            ['attachment 7 object missing'],
            (new MigrationVerifier($this->storage(['b' => 2]), $files))->verify($missingObject),
        );

        $sizeMismatch = new Email([
            'body' => 'hi',
            'body_s3_path' => 'b',
            'file_ids' => [7],
            'file_s3_paths' => ['7' => 'k7'],
        ]);
        $this->assertSame(
            ['attachment 7 size mismatch'],
            (new MigrationVerifier($this->storage(['b' => 2, 'k7' => 999]), $files))->verify($sizeMismatch),
        );
    }

    private function storage(array $sizes): ObjectStorage
    {
        return new class($sizes) implements ObjectStorage
        {
            public function __construct(private array $sizes) {}

            public function put(string $key, string $contents): void {}

            public function putStream(string $key, $stream, int $expectedBytes): void {}

            public function exists(string $key): bool
            {
                return array_key_exists($key, $this->sizes);
            }

            public function size(string $key): int
            {
                return (int) $this->sizes[$key];
            }

            public function delete(string $key): void {}
        };
    }

    private function fileRepo(array $data): FileRepositoryInterface
    {
        return new class($data) implements FileRepositoryInterface
        {
            public function __construct(private array $data) {}

            public function resolve(array $ids): array
            {
                return $this->data;
            }
        };
    }
}

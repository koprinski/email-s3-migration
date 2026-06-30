<?php

namespace App\Migration\Strategies;

use App\Migration\Contracts\MigrationStrategy;
use App\Migration\Contracts\ObjectStorage;
use App\Migration\DTO\EmailData;
use App\Migration\DTO\MigrationResult;
use App\Migration\ValueObjects\S3Path;
use Illuminate\Contracts\Filesystem\Filesystem;

final class AttachmentMigrationStrategy implements MigrationStrategy
{
    public function __construct(
        private readonly ObjectStorage $storage,
        private readonly Filesystem $sourceDisk,
    ) {}

    public function supports(EmailData $email): bool
    {
        return $email->hasAttachments();
    }

    public function migrate(EmailData $email): MigrationResult
    {
        $byId = collect($email->files)->keyBy('id');

        $map = [];
        $skipped = [];

        foreach ($email->fileIds as $fileId) {
            $file = $byId->get($fileId);

            if ($file === null) {
                $skipped[] = ['file_id' => $fileId, 'reason' => 'files row not found'];

                continue;
            }

            if (! $this->sourceDisk->exists($file->path)) {
                $skipped[] = ['file_id' => $fileId, 'reason' => 'source file missing on disk'];

                continue;
            }

            $stream = $this->sourceDisk->readStream($file->path);

            if (! is_resource($stream)) {
                $skipped[] = ['file_id' => $fileId, 'reason' => 'source file unreadable'];

                continue;
            }

            $key = S3Path::attachment($email->id, $file->id, $file->name);
            $expected = (int) $this->sourceDisk->size($file->path);

            try {
                $this->storage->putStream($key, $stream, $expected);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $map[(string) $fileId] = $key;
        }

        return MigrationResult::forAttachments($map, $skipped);
    }
}

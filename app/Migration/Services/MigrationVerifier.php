<?php

namespace App\Migration\Services;

use App\Migration\Contracts\FileRepositoryInterface;
use App\Migration\Contracts\ObjectStorage;
use App\Models\Email;

final class MigrationVerifier
{
    public function __construct(
        private readonly ObjectStorage $storage,
        private readonly FileRepositoryInterface $files,
    ) {}

    /** human-readable problems; empty means verified */
    public function verify(Email $email): array
    {
        $problems = [];

        if ($email->body_s3_path === null) {
            $problems[] = 'missing body_s3_path';
        } elseif (! $this->storage->exists($email->body_s3_path)) {
            $problems[] = 'body object missing';
        } elseif ($email->body !== null && $this->storage->size($email->body_s3_path) !== strlen($email->body)) {
            $problems[] = 'body size mismatch';
        }

        $expectedSizes = collect($this->files->resolve(array_map('intval', $email->file_ids ?? [])))
            ->pluck('size', 'id');

        foreach (($email->file_s3_paths ?? []) as $fileId => $key) {
            if (! $this->storage->exists($key)) {
                $problems[] = "attachment {$fileId} object missing";

                continue;
            }

            $expected = $expectedSizes->get((int) $fileId);
            if ($expected !== null && $this->storage->size($key) !== $expected) {
                $problems[] = "attachment {$fileId} size mismatch";
            }
        }

        return $problems;
    }
}

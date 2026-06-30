<?php

namespace App\Migration\Contracts;

use App\Models\Email;
use Illuminate\Support\Collection;

interface EmailRepositoryInterface
{
    /**
     * Keyset stream of un-migrated ids (WHERE migrated_at IS NULL AND id > cursor), O(1) memory regardless of table size.
     */
    public function streamUnmigratedIds(int $chunk, ?int $fromId = null, ?int $limit = null): iterable;

    public function countUnmigrated(?int $fromId = null, ?int $limit = null): int;

    /** A fresh row for migration, or null if it vanished. */
    public function findForMigration(int $id): ?Email;

    /**
     * Batch-load un-migrated rows for a set of ids (used by chunk jobs).
     */
    public function findManyUnmigrated(array $ids): Collection;

    /**
     * Compare-and-set: persist the S3 paths and stamp migrated_at ONLY while the row is still un-migrated. Returns false (0 rows affected) when another worker already claimed it — exactly-once without locking.
     */
    public function markMigrated(int $id, ?string $bodyS3Path, array $fileS3Paths): bool;
}

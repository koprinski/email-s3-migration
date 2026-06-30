<?php

namespace App\Migration\Repositories;

use App\Migration\Contracts\EmailRepositoryInterface;
use App\Models\Email;
use Illuminate\Support\Collection;

final class EloquentEmailRepository implements EmailRepositoryInterface
{
    public function streamUnmigratedIds(int $chunk, ?int $fromId = null, ?int $limit = null): iterable
    {
        $cursor = $fromId ?? 0;
        $remaining = $limit;

        while (true) {
            $take = $remaining === null ? $chunk : min($chunk, $remaining);
            if ($take <= 0) {
                break;
            }

            $ids = Email::query()
                ->unmigrated()
                ->where('id', '>', $cursor)
                ->orderBy('id')
                ->limit($take)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            foreach ($ids as $id) {
                yield (int) $id;
            }

            $cursor = (int) $ids->last();

            if ($remaining !== null) {
                $remaining -= $ids->count();
            }
        }
    }

    public function countUnmigrated(?int $fromId = null, ?int $limit = null): int
    {
        $count = Email::query()
            ->unmigrated()
            ->when($fromId !== null, fn ($q) => $q->where('id', '>', $fromId))
            ->count();

        return $limit !== null ? min($count, $limit) : $count;
    }

    public function findForMigration(int $id): ?Email
    {
        return Email::query()->whereKey($id)->unmigrated()->first();
    }

    public function findManyUnmigrated(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return Email::query()
            ->whereIn('id', $ids)
            ->unmigrated()
            ->get()
            ->keyBy('id');
    }

    public function markMigrated(int $id, ?string $bodyS3Path, array $fileS3Paths): bool
    {
        $affected = Email::query()
            ->whereKey($id)
            ->unmigrated()
            ->update([
                'body_s3_path' => $bodyS3Path,
                'file_s3_paths' => json_encode($fileS3Paths),
                'migrated_at' => now(),
            ]);

        return $affected === 1;
    }

    public function streamMigratedUnverifiedIds(int $chunk): iterable
    {
        $cursor = 0;

        while (true) {
            $ids = Email::query()
                ->migratedUnverified()
                ->where('id', '>', $cursor)
                ->orderBy('id')
                ->limit($chunk)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            foreach ($ids as $id) {
                yield (int) $id;
            }

            $cursor = (int) $ids->last();
        }
    }

    public function countMigratedUnverified(): int
    {
        return Email::query()->migratedUnverified()->count();
    }

    public function markVerified(int $id): bool
    {
        $affected = Email::query()
            ->whereKey($id)
            ->migratedUnverified()
            ->update(['verified_at' => now()]);

        return $affected === 1;
    }
}

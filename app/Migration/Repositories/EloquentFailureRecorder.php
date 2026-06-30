<?php

namespace App\Migration\Repositories;

use App\Migration\Contracts\FailureRecorderInterface;
use App\Migration\Enums\MigrationPhase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class EloquentFailureRecorder implements FailureRecorderInterface
{
    public function record(string $runId, int $emailId, MigrationPhase $phase, Throwable $e, int $attempts = 1): void
    {
        DB::table('migration_failures')->insert([
            'run_id' => $runId,
            'email_id' => $emailId,
            'phase' => $phase->value,
            'exception_class' => $e::class,
            'message' => Str::limit($e->getMessage(), 1000),
            'attempts' => $attempts,
            'failed_at' => now(),
        ]);
    }

    public function streamFailedEmailIds(int $chunk): iterable
    {
        $cursor = 0;

        while (true) {
            $ids = DB::table('migration_failures')
                ->where('email_id', '>', $cursor)
                ->distinct()
                ->orderBy('email_id')
                ->limit($chunk)
                ->pluck('email_id');

            if ($ids->isEmpty()) {
                break;
            }

            foreach ($ids as $id) {
                yield (int) $id;
            }

            $cursor = (int) $ids->last();
        }
    }

    public function countFailedEmails(): int
    {
        return (int) DB::table('migration_failures')->distinct()->count('email_id');
    }
}

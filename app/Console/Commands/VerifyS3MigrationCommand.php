<?php

namespace App\Console\Commands;

use App\Migration\Contracts\EmailRepositoryInterface;
use App\Migration\Contracts\FailureRecorderInterface;
use App\Migration\Enums\MigrationPhase;
use App\Migration\Services\MigrationVerifier;
use App\Models\Email;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

class VerifyS3MigrationCommand extends Command
{
    protected $signature = 'emails:verify-s3
        {--chunk= : Rows scanned per keyset page}
        {--limit= : Verify at most N rows}';

    protected $description = 'Verify migrated emails against S3 (existence + byte size) and stamp verified_at.';

    public function handle(
        EmailRepositoryInterface $emails,
        MigrationVerifier $verifier,
        FailureRecorderInterface $failures,
    ): int {
        $chunk = (int) ($this->option('chunk') ?: config('migration.chunk_size'));
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $runId = strtolower((string) Str::ulid());

        $total = $emails->countMigratedUnverified();
        if ($limit !== null) {
            $total = min($total, $limit);
        }

        $this->components->info("Verify {$runId} | {$total} migrated-unverified row(s)");
        if ($total === 0) {
            $this->components->info('Nothing to verify.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $verified = 0;
        $problems = 0;
        $seen = 0;

        foreach ($emails->streamMigratedUnverifiedIds($chunk) as $id) {
            if ($limit !== null && $seen >= $limit) {
                break;
            }
            $seen++;

            $email = Email::find($id);
            if ($email !== null) {
                $issues = $verifier->verify($email);

                if ($issues === [] && $emails->markVerified($id)) {
                    $verified++;
                } elseif ($issues !== []) {
                    $problems++;
                    $failures->record($runId, $id, MigrationPhase::Verify, new RuntimeException(implode('; ', $issues)));
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->components->info("Verified {$verified}, problems {$problems}.");

        return $problems > 0 ? self::FAILURE : self::SUCCESS;
    }
}

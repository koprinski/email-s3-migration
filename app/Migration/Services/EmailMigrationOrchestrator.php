<?php

namespace App\Migration\Services;

use App\Migration\Bus\MigrateEmail;
use App\Migration\Bus\MigrateEmailHandler;
use App\Migration\Contracts\FailureRecorderInterface;
use App\Migration\DTO\HandlerOutcome;
use App\Migration\DTO\MigrationSummary;
use App\Migration\Enums\MigrationPhase;
use App\Migration\Exceptions\EmailPersistException;
use Psr\Log\LoggerInterface;
use Throwable;

final class EmailMigrationOrchestrator
{
    public function __construct(
        private readonly MigrateEmailHandler $handler,
        private readonly FailureRecorderInterface $failures,
        private readonly LoggerInterface $logger,
    ) {}

    public function run(string $runId, iterable $ids): MigrationSummary
    {
        $summary = new MigrationSummary;

        foreach ($ids as $id) {
            $summary->processed++;

            try {
                $outcome = $this->handler->handle(new MigrateEmail($id));
                $this->applyOutcome($summary, $outcome, $runId, $id);
            } catch (Throwable $e) {
                $summary->failed++;
                $this->failures->record($runId, $id, $this->phaseFor($e), $e);
                $this->logger->error('email migration failed', [
                    'run_id' => $runId,
                    'email_id' => $id,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    private function applyOutcome(MigrationSummary $summary, HandlerOutcome $outcome, string $runId, int $id): void
    {
        if ($outcome->status === HandlerOutcome::SKIPPED) {
            $summary->skipped++;

            return;
        }

        $summary->migrated++;
        $summary->attachments += $outcome->attachmentsUploaded();

        $this->logger->debug('email migrated', [
            'run_id' => $runId,
            'email_id' => $id,
            'attachments' => $outcome->attachmentsUploaded(),
            'files_skipped' => $outcome->filesSkipped(),
        ]);
    }

    private function phaseFor(Throwable $e): MigrationPhase
    {
        return match (true) {
            $e instanceof EmailPersistException => MigrationPhase::Persist,
            default => MigrationPhase::Migrate,
        };
    }
}

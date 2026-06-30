<?php

namespace App\Migration\Bus;

use App\Migration\Contracts\EmailRepositoryInterface;
use App\Migration\Contracts\FileRepositoryInterface;
use App\Migration\DTO\EmailData;
use App\Migration\DTO\HandlerOutcome;
use App\Migration\DTO\MigrationResult;
use App\Migration\Exceptions\EmailPersistException;
use App\Models\Email;
use Throwable;

final class MigrateEmailHandler
{
    public function __construct(
        private readonly EmailRepositoryInterface $emails,
        private readonly FileRepositoryInterface $files,
        private readonly array $strategies,
    ) {}

    public function handle(MigrateEmail $command): HandlerOutcome
    {
        $email = $this->emails->findForMigration($command->emailId);

        if ($email === null) {
            return HandlerOutcome::skipped();
        }

        $data = $this->toData($email);

        $result = MigrationResult::empty();
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($data)) {
                $result = $result->merge($strategy->migrate($data));
            }
        }

        try {
            $won = $this->emails->markMigrated($email->id, $result->bodyS3Path, $result->fileS3Paths);
        } catch (Throwable $e) {
            throw new EmailPersistException("Failed to persist migration for email {$email->id}: {$e->getMessage()}", 0, $e);
        }

        return $won ? HandlerOutcome::migrated($result) : HandlerOutcome::skipped();
    }

    private function toData(Email $email): EmailData
    {
        $fileIds = array_map('intval', $email->file_ids ?? []);

        return new EmailData(
            id: $email->id,
            body: $email->body,
            fileIds: $fileIds,
            files: $this->files->resolve($fileIds),
        );
    }
}

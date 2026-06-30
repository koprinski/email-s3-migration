<?php

namespace App\Migration\DTO;

final class MigrationSummary
{
    public function __construct(
        public int $processed = 0,
        public int $migrated = 0,
        public int $skipped = 0,
        public int $failed = 0,
        public int $attachments = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'processed' => $this->processed,
            'migrated' => $this->migrated,
            'skipped_existing' => $this->skipped,
            'failed' => $this->failed,
            'attachments_uploaded' => $this->attachments,
        ];
    }
}

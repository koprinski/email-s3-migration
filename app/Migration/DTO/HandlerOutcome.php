<?php

namespace App\Migration\DTO;

final class HandlerOutcome
{
    public const MIGRATED = 'migrated';

    public const SKIPPED = 'skipped';

    private function __construct(
        public readonly string $status,
        public readonly ?MigrationResult $result = null,
    ) {}

    public static function migrated(MigrationResult $result): self
    {
        return new self(self::MIGRATED, $result);
    }

    public static function skipped(): self
    {
        return new self(self::SKIPPED);
    }

    public function attachmentsUploaded(): int
    {
        return $this->result !== null ? count($this->result->fileS3Paths) : 0;
    }

    public function filesSkipped(): int
    {
        return $this->result !== null ? count($this->result->skipped) : 0;
    }
}

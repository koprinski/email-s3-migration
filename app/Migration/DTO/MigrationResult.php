<?php

namespace App\Migration\DTO;

final class MigrationResult
{
    public function __construct(
        public readonly ?string $bodyS3Path = null,
        public readonly array $fileS3Paths = [],
        public readonly array $skipped = [],
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    public static function forBody(string $path): self
    {
        return new self(bodyS3Path: $path);
    }

    public static function forAttachments(array $map, array $skipped = []): self
    {
        return new self(fileS3Paths: $map, skipped: $skipped);
    }

    public function merge(self $other): self
    {
        return new self(
            $other->bodyS3Path ?? $this->bodyS3Path,
            $this->fileS3Paths + $other->fileS3Paths,
            array_merge($this->skipped, $other->skipped),
        );
    }
}

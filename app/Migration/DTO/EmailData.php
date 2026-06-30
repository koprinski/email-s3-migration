<?php

namespace App\Migration\DTO;

final class EmailData
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $body,
        public readonly array $fileIds,
        public readonly array $files,
    ) {}

    public function hasBody(): bool
    {
        return $this->body !== null && $this->body !== '';
    }

    public function hasAttachments(): bool
    {
        return $this->fileIds !== [];
    }
}

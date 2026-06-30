<?php

namespace App\Migration\DTO;

final class FileData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $path,
        public readonly int $size,
        public readonly string $type,
    ) {}
}

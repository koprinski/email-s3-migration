<?php

namespace App\Migration\Contracts;

interface ObjectStorage
{
    public function put(string $key, string $contents): void;

    public function putStream(string $key, $stream, int $expectedBytes): void;

    public function exists(string $key): bool;

    public function size(string $key): int;

    public function delete(string $key): void;
}

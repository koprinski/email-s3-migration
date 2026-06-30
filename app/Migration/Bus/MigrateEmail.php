<?php

namespace App\Migration\Bus;

final class MigrateEmail
{
    public function __construct(
        public readonly int $emailId,
    ) {}
}

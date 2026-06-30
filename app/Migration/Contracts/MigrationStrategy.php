<?php

namespace App\Migration\Contracts;

use App\Migration\DTO\EmailData;
use App\Migration\DTO\MigrationResult;

interface MigrationStrategy
{
    public function supports(EmailData $email): bool;

    public function migrate(EmailData $email): MigrationResult;
}

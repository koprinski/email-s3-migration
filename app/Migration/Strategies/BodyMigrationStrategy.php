<?php

namespace App\Migration\Strategies;

use App\Migration\Contracts\MigrationStrategy;
use App\Migration\Contracts\ObjectStorage;
use App\Migration\DTO\EmailData;
use App\Migration\DTO\MigrationResult;
use App\Migration\ValueObjects\S3Path;

final class BodyMigrationStrategy implements MigrationStrategy
{
    public function __construct(private readonly ObjectStorage $storage) {}

    public function supports(EmailData $email): bool
    {
        return $email->hasBody();
    }

    public function migrate(EmailData $email): MigrationResult
    {
        $key = S3Path::body($email->id);
        $this->storage->put($key, (string) $email->body);

        return MigrationResult::forBody($key);
    }
}

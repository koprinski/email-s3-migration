<?php

namespace App\Providers;

use App\Migration\Bus\MigrateEmailHandler;
use App\Migration\Contracts\EmailRepositoryInterface;
use App\Migration\Contracts\FailureRecorderInterface;
use App\Migration\Contracts\FileRepositoryInterface;
use App\Migration\Contracts\ObjectStorage;
use App\Migration\Repositories\EloquentEmailRepository;
use App\Migration\Repositories\EloquentFailureRecorder;
use App\Migration\Repositories\EloquentFileRepository;
use App\Migration\Services\StreamingS3Uploader;
use App\Migration\Strategies\AttachmentMigrationStrategy;
use App\Migration\Strategies\BodyMigrationStrategy;
use App\Migration\Support\RetryingRunner;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class MigrationServiceProvider extends ServiceProvider
{
    public array $bindings = [
        EmailRepositoryInterface::class => EloquentEmailRepository::class,
        FileRepositoryInterface::class => EloquentFileRepository::class,
        FailureRecorderInterface::class => EloquentFailureRecorder::class,
    ];

    public function register(): void
    {
        $this->app->bind(RetryingRunner::class, function () {
            $retry = config('migration.retry');

            return new RetryingRunner(
                tries: (int) $retry['tries'],
                baseDelayMs: (int) $retry['base_delay_ms'],
                maxDelayMs: (int) $retry['max_delay_ms'],
            );
        });

        $this->app->bind(ObjectStorage::class, fn ($app) => new StreamingS3Uploader(
            Storage::disk(config('migration.s3_disk')),
            $app->make(RetryingRunner::class),
            $app->make(LoggerInterface::class),
        ));

        $this->app->bind(BodyMigrationStrategy::class, fn ($app) => new BodyMigrationStrategy(
            $app->make(ObjectStorage::class),
        ));

        $this->app->bind(AttachmentMigrationStrategy::class, fn ($app) => new AttachmentMigrationStrategy(
            $app->make(ObjectStorage::class),
            Storage::disk(config('migration.source_disk')),
        ));

        $this->app->tag([
            BodyMigrationStrategy::class,
            AttachmentMigrationStrategy::class,
        ], 'migration.strategies');

        $this->app->bind(MigrateEmailHandler::class, fn ($app) => new MigrateEmailHandler(
            $app->make(EmailRepositoryInterface::class),
            $app->make(FileRepositoryInterface::class),
            array_values(iterator_to_array($app->tagged('migration.strategies'))),
        ));
    }
}

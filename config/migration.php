<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Disks (reuse the framework's built-in disks)
    |--------------------------------------------------------------------------
    | source_disk: the "local" disk — where the original attachments live on the
    |              server's hard disk. The migration streams from here; finalize
    |              deletes from here.
    | s3_disk:     the "s3" disk — the MinIO/S3 destination the migration writes to.
    |
    | Upload safety does not rely on the disk's `throw` flag: the uploader treats a
    | falsey write result or a post-write size mismatch as a hard failure.
    */
    'source_disk' => env('MIGRATION_SOURCE_DISK', 'local'),
    's3_disk' => env('MIGRATION_S3_DISK', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Throughput
    |--------------------------------------------------------------------------
    | chunk_size: number of email ids processed per queued job / per keyset page.
    | queue:      the queue name the chunk jobs are dispatched onto.
    */
    'chunk_size' => (int) env('MIGRATION_CHUNK_SIZE', 500),
    'queue' => env('MIGRATION_QUEUE', 'migrations'),

    /*
    |--------------------------------------------------------------------------
    | Retry policy (single source of truth — sync runner and queue jobs read this)
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'tries' => (int) env('MIGRATION_RETRY_TRIES', 3),
        'base_delay_ms' => (int) env('MIGRATION_RETRY_BASE_MS', 200),
        'max_delay_ms' => (int) env('MIGRATION_RETRY_MAX_MS', 5000),
    ],
];

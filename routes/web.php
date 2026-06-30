<?php

use Illuminate\Support\Facades\Route;

/*
 * This is a headless, CLI-driven service (see the emails:* artisan commands).
 * The root route returns a small machine-readable description and a health flag
 * rather than a UI.
 */
Route::get('/', fn () => response()->json([
    'app' => config('app.name'),
    'status' => 'ok',
    'description' => 'Migrates email bodies and attachments from PostgreSQL to S3.',
    'commands' => [
        'emails:seed',
        'emails:migrate-to-s3',
        'emails:verify-s3',
        'emails:finalize-s3',
    ],
]));

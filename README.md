# Email → S3 Migration

A Laravel Artisan tool that migrates historical email data (HTML bodies and on-disk
attachments) from a large PostgreSQL `emails` table to S3 (MinIO locally), so the
`body` column and the local files can later be **safely** removed — shrinking the
table and speeding up queries.

The migration is **idempotent, resumable, and scalable** (queue + batched workers),
and is split into three explicit phases so data is never destroyed before its copy in
S3 has been independently verified:

```
emails:migrate-to-s3   →   emails:verify-s3   →   emails:finalize-s3
   (upload + stamp)         (size/existence)       (delete files, drop body)
```

---

## Tech stack

Laravel 12 · PHP 8.2 · PostgreSQL 16 · Redis (queue) · S3 via `league/flysystem-aws-s3-v3`
· MinIO (local S3) · PHPUnit · Docker Compose.

---

## Quick start (Docker)

```bash
cp .env.example .env

# Build + start everything (php-fpm, nginx, postgres, redis, minio, a queue worker)
docker compose up -d --build

# Install dependencies and generate the app key
docker compose exec php-fpm composer install
docker compose exec php-fpm php artisan key:generate

# Create the schema and seed a realistic dataset (100k emails, 1–3 files each)
docker compose exec php-fpm php artisan migrate
docker compose exec php-fpm php artisan emails:seed --count=100000
#   …or a quick set for a smoke test:
docker compose exec php-fpm php artisan emails:seed --count=2000
```

Services: app on <http://localhost:8080>, MinIO console on <http://localhost:9001>
(`minioadmin` / `minioadmin`).

### Run the migration

```bash
# Dispatch the batch of chunk jobs (always queued); workers do the upload
docker compose exec php-fpm php artisan emails:migrate-to-s3
docker compose up -d --scale queue-worker=4        # more throughput

# Phase 2 + 3 — verify, then the guarded teardown
docker compose exec php-fpm php artisan emails:verify-s3
docker compose exec php-fpm php artisan emails:finalize-s3        # add --force to skip the prompt
```

> Re-running `emails:migrate-to-s3` is always safe — already-migrated rows are skipped.

---

## Commands

| Command | Purpose | Key options |
|---|---|---|
| `emails:seed` | Seed `emails` + `files` and write real on-disk attachments | `--count=` `--chunk=` `--files-min/max=` |
| `emails:migrate-to-s3` | Dispatch a queued batch that uploads bodies + attachments and stamps `migrated_at` | `--chunk=` `--limit=` `--from-id=` `--retry-failed` `--force` |
| `emails:verify-s3` | Confirm S3 objects exist with the right size, stamp `verified_at` | `--chunk=` `--limit=` |
| `emails:finalize-s3` | After full verification: delete local files, drop `body` | `--force` |

`emails:migrate-to-s3` always queues a batch of chunk jobs and exits 0 once the batch is
**dispatched** (not finished); a queue worker performs the upload, and failures land in
the `migration_failures` table.

Two guards prevent accidental double runs: a cache lock rejects a second invocation
while one is still dispatching, and the command refuses (unless `--force`) while a
previous migration batch still has jobs in flight. Attachments are stored once per
`files` row (`files/{file_id}/…`), so a file shared by many emails is uploaded a
single time.

---

## Environment variables

The important ones (see `.env.example` for all):

| Variable | Meaning |
|---|---|
| `DB_*` | PostgreSQL connection (`DB_HOST=postgres` in Docker, `127.0.0.1` from the host) |
| `QUEUE_CONNECTION` | `database` (shipped default); `redis` for throughput, `sync` for a workerless run |
| `AWS_*` | S3/MinIO credentials, bucket, endpoint — `AWS_USE_PATH_STYLE_ENDPOINT=true` is required for MinIO |
| `MIGRATION_CHUNK_SIZE` | Emails per queued job / keyset page (default 500) |
| `MIGRATION_QUEUE` | Queue the chunk jobs are dispatched onto (default `migrations`) |
| `MIGRATION_RETRY_*` | `_TRIES` / `_BASE_MS` / `_MAX_MS` — retry budget for transient S3 failures |

`config/migration.php` centralises the source disk, S3 disk, chunk size, queue name
and retry policy.

---

## Testing

```bash
# Unit + Feature (no network — S3 is faked via Storage::fake)
docker compose exec php-fpm php artisan test --testsuite=Unit,Feature

# Integration against the real MinIO adapter (streaming + multipart round-trip)
docker compose exec -e RUN_MINIO_TESTS=1 php-fpm php artisan test --testsuite=Integration
```

The suite — 49 unit/feature tests plus an opt-in MinIO integration test — covers the
deterministic key scheme, the classified retry policy, the streaming uploader's size
verification, both strategies (incl. skipping missing/unreadable attachments), the
compare-and-set repository and keyset pagination, the command's batch dispatch and
`--retry-failed`, the orchestrator's failure recording, and the verify/finalize guards.

**Coverage** (measured with PCOV): ~100% of the migration module — `app/Migration` and
the four commands — and ~84% across all of `app/`; the untested remainder is the data
seeder and Laravel's default scaffolding.

---

## Project layout

```
app/
  Console/Commands/        emails:seed | migrate-to-s3 | verify-s3 | finalize-s3
  Migration/
    Contracts/             EmailRepositoryInterface, FileRepositoryInterface, ObjectStorage, MigrationStrategy, FailureRecorderInterface
    Strategies/            BodyMigrationStrategy, AttachmentMigrationStrategy
    Repositories/          Eloquent{Email,File,FailureRecorder} repositories
    Services/              StreamingS3Uploader, EmailMigrationOrchestrator, MigrationVerifier
    Bus/                   MigrateEmail (command) + MigrateEmailHandler
    Jobs/                  MigrateEmailChunk (queued, batchable)
    DTO/ ValueObjects/ Enums/ Exceptions/ Support/
  Providers/MigrationServiceProvider.php
database/{migrations,factories,seeders}
docker/{php/Dockerfile, nginx/default.conf}
tests/{Unit,Feature,Integration}
```

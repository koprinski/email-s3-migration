<?php

namespace App\Console\Commands;

use App\Models\Email;
use App\Models\File;
use App\Support\HtmlBodyGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SeedEmailsCommand extends Command
{
    protected $signature = 'emails:seed
        {--count=100000 : Number of email rows to create}
        {--chunk=2000 : Rows inserted per transaction}
        {--files-min=1 : Minimum attachments per email}
        {--files-max=3 : Maximum attachments per email}';

    protected $description = 'Seed the emails + files tables with fake data and real on-disk attachments.';

    private const BODY_POOL = 50;

    private const EXTENSIONS = ['pdf', 'png', 'jpg', 'txt', 'csv'];

    public function handle(): int
    {
        $count = max(0, (int) $this->option('count'));
        $chunk = max(100, (int) $this->option('chunk'));
        $filesMin = max(0, (int) $this->option('files-min'));
        $filesMax = max($filesMin, (int) $this->option('files-max'));

        $disk = Storage::disk(config('migration.source_disk'));

        $this->info("Seeding {$count} emails (1–{$filesMax} files each)…");

        DB::statement('TRUNCATE TABLE emails, files, migration_failures RESTART IDENTITY CASCADE');

        $bodyPool = Collection::times(self::BODY_POOL, fn (int $i) => HtmlBodyGenerator::make(10_240 + $i * 128, $i));

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $created = 0;
        while ($created < $count) {
            $batch = min($chunk, $count - $created);

            DB::transaction(function () use ($batch, $filesMin, $filesMax, $disk, $bodyPool) {
                [$fileRows, $perEmailCounts] = $this->buildFileRows($batch, $filesMin, $filesMax);

                $startId = (int) (File::max('id') ?? 0);

                $fileRows->chunk(1_000)->each(fn (Collection $slice) => File::insert($slice->all()));

                $fileRows->each(fn (array $row) => $disk->put($row['path'], $this->stubContents($row['name'], $row['size'])));

                $this->insertEmails($perEmailCounts, $startId, $bodyPool);
            });

            $created += $batch;
            $bar->advance($batch);
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. emails={$count}, files=".File::count());

        return self::SUCCESS;
    }

    /**
     * Build the file rows for one batch and the per-email file counts.
     */
    private function buildFileRows(int $batch, int $filesMin, int $filesMax): array
    {
        $now = Carbon::now()->toDateTimeString();

        $perEmailCounts = Collection::times(
            $batch,
            fn () => $filesMax > 0 ? random_int($filesMin, $filesMax) : 0,
        );

        $fileRows = $perEmailCounts->flatMap(fn (int $n) => Collection::times($n, function () use ($now) {
            $ext = Arr::random(self::EXTENSIONS);
            $name = Str::slug(Str::random(8)).'.'.$ext;

            return [
                'name' => $name,
                'path' => 'attachments/'.Str::ulid().'_'.$name,
                'size' => random_int(512, 4_096),
                'type' => $this->mimeFor($ext),
                'created_at' => $now,
            ];
        }));

        return [$fileRows, $perEmailCounts];
    }

    /**
     * Insert the email rows, linking each to its freshly-inserted file ids.
     */
    private function insertEmails(Collection $perEmailCounts, int $startId, Collection $bodyPool): void
    {
        $cursor = $startId;

        $emailRows = $perEmailCounts->map(function (int $n) use (&$cursor, $bodyPool) {
            $ids = $n > 0 ? range($cursor + 1, $cursor + $n) : [];
            $cursor += $n;

            $created = Carbon::now()->subDays(random_int(1, 720))->subMinutes(random_int(0, 1_440));

            return [
                'client_id' => random_int(1, 10_000),
                'loan_id' => random_int(1, 50_000),
                'email_template_id' => random_int(1, 50),
                'receiver_email' => 'user'.random_int(1, 1_000_000).'@example.com',
                'sender_email' => 'notifications@example.com',
                'subject' => 'Notification '.Str::random(12),
                'body' => $bodyPool->random(),
                'file_ids' => json_encode($ids),
                'body_s3_path' => null,
                'file_s3_paths' => null,
                'migrated_at' => null,
                'verified_at' => null,
                'created_at' => $created->toDateTimeString(),
                'sent_at' => $created->copy()->addMinutes(random_int(1, 120))->toDateTimeString(),
            ];
        });

        $emailRows->chunk(1_000)->each(fn (Collection $slice) => Email::insert($slice->all()));
    }

    private function stubContents(string $name, int $size): string
    {
        $header = "STUB ATTACHMENT {$name}\n";

        return $header.str_repeat('x', max(0, $size - strlen($header)));
    }

    private function mimeFor(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            default => 'application/octet-stream',
        };
    }
}

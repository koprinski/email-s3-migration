<?php

namespace App\Console\Commands;

use App\Migration\Contracts\EmailRepositoryInterface;
use App\Models\File;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class FinalizeS3MigrationCommand extends Command
{
    protected $signature = 'emails:finalize-s3
        {--force : Skip the confirmation prompt}';

    protected $description = 'After full verification, delete local attachment files and drop the emails.body column.';

    public function handle(EmailRepositoryInterface $emails): int
    {
        $unmigrated = $emails->countUnmigrated();
        $unverified = $emails->countMigratedUnverified();

        if ($unmigrated > 0 || $unverified > 0) {
            $this->components->error(
                "Refusing to finalize: {$unmigrated} unmigrated and {$unverified} unverified row(s) remain. "
                .'Every row must be migrated AND verified first.'
            );

            return self::FAILURE;
        }

        if (! $this->option('force')
            && ! $this->confirm('This deletes local attachment files and DROPS emails.body. Continue?')) {
            $this->components->info('Aborted.');

            return self::SUCCESS;
        }

        $deleted = $this->deleteLocalFiles();
        $this->components->info("Deleted {$deleted} local attachment file(s).");

        $this->dropBodyColumn();
        $this->components->info('Dropped emails.body column.');

        $this->components->info('Finalize complete.');

        return self::SUCCESS;
    }

    private function deleteLocalFiles(): int
    {
        $disk = Storage::disk(config('migration.source_disk'));
        $deleted = 0;

        File::query()->orderBy('id')->chunkById(1000, function ($files) use ($disk, &$deleted) {
            $deleted += $files->filter(fn (File $file) => $disk->exists($file->path))
                ->each(fn (File $file) => $disk->delete($file->path))
                ->count();
        });

        return $deleted;
    }

    private function dropBodyColumn(): void
    {
        if (Schema::hasColumn('emails', 'body')) {
            Schema::table('emails', fn (Blueprint $table) => $table->dropColumn('body'));
        }
    }
}

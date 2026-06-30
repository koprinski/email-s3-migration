<?php

namespace Database\Factories;

use App\Models\File;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileFactory extends Factory
{
    protected $model = File::class;

    public function definition(): array
    {
        $extension = fake()->randomElement(['pdf', 'png', 'jpg', 'txt', 'csv', 'docx']);
        $name = fake()->slug(2).'.'.$extension;

        return [
            'name' => $name,
            'path' => 'attachments/'.Str::ulid().'_'.$name,
            'size' => fake()->numberBetween(2_048, 64_512),
            'type' => self::mimeFor($extension),
            'created_at' => fake()->dateTimeBetween('-2 years', 'now'),
        ];
    }

    /**
     * Write a real stub file to the source disk and sync the recorded size.
     */
    public function onDisk(?string $disk = null): static
    {
        return $this->afterCreating(function (File $file) use ($disk) {
            $disk ??= config('migration.source_disk');
            $contents = "STUB ATTACHMENT {$file->name}\n"
                .str_repeat('x', max(0, $file->size - 32));

            Storage::disk($disk)->put($file->path, $contents);
            $file->size = Storage::disk($disk)->size($file->path);
            $file->save();
        });
    }

    private static function mimeFor(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };
    }
}

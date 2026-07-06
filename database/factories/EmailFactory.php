<?php

namespace Database\Factories;

use App\Models\Email;
use App\Models\File;
use App\Support\HtmlBodyGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailFactory extends Factory
{
    protected $model = Email::class;

    public function definition(): array
    {
        $created = fake()->dateTimeBetween('-2 years', '-1 day');

        return [
            'client_id' => fake()->numberBetween(1, 10_000),
            'loan_id' => fake()->numberBetween(1, 50_000),
            'email_template_id' => fake()->numberBetween(1, 50),
            'receiver_email' => fake()->safeEmail(),
            'sender_email' => 'notifications@example.com',
            'subject' => fake()->sentence(6),
            'body' => HtmlBodyGenerator::make(10_240, fake()->numberBetween(1, 1_000_000)),
            'file_ids' => [],
            'body_s3_path' => null,
            'file_s3_paths' => null,
            'migrated_at' => null,
            'verified_at' => null,
            'created_at' => $created,
            'sent_at' => fake()->optional(0.9)->dateTimeBetween($created, 'now'),
        ];
    }

    /**
     * Attach real files (rows + on-disk stubs) and link their ids.
     */
    public function withFiles(?int $count = null, ?string $disk = null): static
    {
        return $this->afterCreating(function (Email $email) use ($count, $disk) {
            $count ??= random_int(1, 3);

            $files = File::factory()->count($count)->onDisk($disk)->create();

            $email->file_ids = $files->pluck('id')->all();
            $email->save();
        });
    }

    /**
     * Put the email in a migrated state for idempotency tests.
     */
    public function migrated(): static
    {
        return $this->afterCreating(function (Email $email) {
            $paths = [];
            foreach ($email->file_ids ?? [] as $fileId) {
                $paths[(string) $fileId] = "files/{$fileId}/file";
            }

            $email->forceFill([
                'body_s3_path' => "emails/{$email->id}/body.html",
                'file_s3_paths' => $paths,
                'migrated_at' => now(),
            ])->save();
        });
    }
}

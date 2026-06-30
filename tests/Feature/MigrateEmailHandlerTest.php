<?php

namespace Tests\Feature;

use App\Migration\Bus\MigrateEmail;
use App\Migration\Bus\MigrateEmailHandler;
use App\Migration\DTO\HandlerOutcome;
use App\Models\Email;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrateEmailHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_uploads_then_stamps_migrated(): void
    {
        Storage::fake('s3');
        Storage::fake('local');
        $email = Email::factory()->withFiles(2)->create();

        $outcome = $this->app->make(MigrateEmailHandler::class)
            ->handle(new MigrateEmail($email->id));

        $this->assertSame(HandlerOutcome::MIGRATED, $outcome->status);
        $email->refresh();
        $this->assertNotNull($email->migrated_at);
        $this->assertSame("emails/{$email->id}/body.html", $email->body_s3_path);
        $this->assertCount(2, $email->file_s3_paths);
        Storage::disk('s3')->assertExists("emails/{$email->id}/body.html");
    }

    public function test_handle_skips_already_migrated(): void
    {
        Storage::fake('s3');
        $email = Email::factory()->migrated()->create();

        $outcome = $this->app->make(MigrateEmailHandler::class)
            ->handle(new MigrateEmail($email->id));

        $this->assertSame(HandlerOutcome::SKIPPED, $outcome->status);
    }
}

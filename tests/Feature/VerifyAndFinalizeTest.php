<?php

namespace Tests\Feature;

use App\Migration\Enums\MigrationPhase;
use App\Migration\Services\EmailMigrationOrchestrator;
use App\Models\Email;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerifyAndFinalizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_stamps_verified_at(): void
    {
        Storage::fake('s3');
        Storage::fake('local');
        Email::factory()->count(4)->withFiles(2)->create();
        $this->migrate();

        $this->artisan('emails:verify-s3')->assertSuccessful();

        $this->assertSame(4, Email::whereNotNull('verified_at')->count());
    }

    public function test_verify_records_a_problem_when_an_s3_object_is_missing(): void
    {
        Storage::fake('s3');
        Storage::fake('local');
        Email::factory()->count(3)->withFiles(1)->create();
        $this->migrate();

        $corrupt = Email::query()->orderBy('id')->first();
        Storage::disk('s3')->delete($corrupt->body_s3_path);

        $this->artisan('emails:verify-s3')->assertFailed();

        $this->assertNull($corrupt->fresh()->verified_at);
        $this->assertSame(2, Email::whereNotNull('verified_at')->count());
        $this->assertDatabaseHas('migration_failures', [
            'email_id' => $corrupt->id,
            'phase' => MigrationPhase::Verify->value,
        ]);
    }

    public function test_verify_reports_nothing_when_there_is_nothing_to_verify(): void
    {
        $this->artisan('emails:verify-s3')->assertSuccessful();
    }

    public function test_verify_honours_the_limit_option(): void
    {
        Storage::fake('s3');
        Storage::fake('local');
        Email::factory()->count(3)->withFiles(1)->create();
        $this->migrate();

        $this->artisan('emails:verify-s3 --limit=1')->assertSuccessful();

        $this->assertSame(1, Email::whereNotNull('verified_at')->count());
    }

    public function test_finalize_aborts_without_confirmation(): void
    {
        Storage::fake('s3');
        Storage::fake('local');
        Email::factory()->count(2)->withFiles(1)->create();
        $this->migrate();
        $this->artisan('emails:verify-s3')->assertSuccessful();

        $this->artisan('emails:finalize-s3')
            ->expectsConfirmation('This deletes local attachment files and DROPS emails.body. Continue?', 'no')
            ->assertSuccessful();

        $this->assertTrue(Schema::hasColumn('emails', 'body'));
        $this->assertGreaterThan(0, count(Storage::disk('local')->allFiles()));
    }

    public function test_finalize_refuses_until_everything_verified(): void
    {
        Storage::fake('s3');
        Storage::fake('local');
        Email::factory()->count(3)->withFiles(1)->create();
        $this->migrate();
        $this->artisan('emails:verify-s3')->assertSuccessful();

        Email::query()->limit(1)->update(['verified_at' => null]);

        $this->artisan('emails:finalize-s3 --force')->assertFailed();
        $this->assertTrue(Schema::hasColumn('emails', 'body'));
    }

    public function test_finalize_drops_body_and_deletes_local_files(): void
    {
        Storage::fake('s3');
        Storage::fake('local');
        Email::factory()->count(3)->withFiles(2)->create();
        $this->migrate();
        $this->artisan('emails:verify-s3')->assertSuccessful();

        $this->assertGreaterThan(0, count(Storage::disk('local')->allFiles()));

        $this->artisan('emails:finalize-s3 --force')->assertSuccessful();

        $this->assertFalse(Schema::hasColumn('emails', 'body'));
        $this->assertSame(0, count(Storage::disk('local')->allFiles()));
    }

    private function migrate(): void
    {
        $this->app->make(EmailMigrationOrchestrator::class)
            ->run('seed', Email::pluck('id')->all());
    }
}

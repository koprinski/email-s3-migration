<?php

namespace Tests\Unit\Migration;

use App\Migration\DTO\MigrationResult;
use PHPUnit\Framework\TestCase;

class MigrationResultTest extends TestCase
{
    public function test_merge_combines_body_attachments_and_skips(): void
    {
        $body = MigrationResult::forBody('emails/1/body.html');
        $attachments = MigrationResult::forAttachments(
            ['7' => 'emails/1/attachments/7_a.pdf'],
            [['file_id' => 8, 'reason' => 'missing']],
        );

        $merged = $body->merge($attachments);

        $this->assertSame('emails/1/body.html', $merged->bodyS3Path);
        $this->assertSame(['7' => 'emails/1/attachments/7_a.pdf'], $merged->fileS3Paths);
        $this->assertCount(1, $merged->skipped);
    }

    public function test_empty_result_has_no_paths(): void
    {
        $empty = MigrationResult::empty();

        $this->assertNull($empty->bodyS3Path);
        $this->assertSame([], $empty->fileS3Paths);
    }
}

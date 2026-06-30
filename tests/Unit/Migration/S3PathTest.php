<?php

namespace Tests\Unit\Migration;

use App\Migration\ValueObjects\S3Path;
use PHPUnit\Framework\TestCase;

class S3PathTest extends TestCase
{
    public function test_body_key_is_deterministic(): void
    {
        $this->assertSame('emails/42/body.html', S3Path::body(42));
    }

    public function test_attachment_key_includes_file_id_and_slugged_name(): void
    {
        $this->assertSame('emails/42/attachments/7_report.pdf', S3Path::attachment(42, 7, 'report.pdf'));
    }

    public function test_attachment_key_neutralises_unsafe_names(): void
    {
        $this->assertSame('emails/1/attachments/9_my-file.pdf', S3Path::attachment(1, 9, 'My File.pdf'));
        $this->assertSame('emails/1/attachments/9_passwd', S3Path::attachment(1, 9, '../../etc/passwd'));
        $this->assertSame('emails/1/attachments/9_file', S3Path::attachment(1, 9, '...'));
    }

    public function test_prefix(): void
    {
        $this->assertSame('emails/5/', S3Path::prefix(5));
    }
}

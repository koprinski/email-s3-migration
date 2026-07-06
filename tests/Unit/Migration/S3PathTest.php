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

    public function test_attachment_key_is_per_file_so_shared_files_map_to_one_object(): void
    {
        $this->assertSame('files/7/report.pdf', S3Path::attachment(7, 'report.pdf'));
    }

    public function test_attachment_key_neutralises_unsafe_names(): void
    {
        $this->assertSame('files/9/my-file.pdf', S3Path::attachment(9, 'My File.pdf'));
        $this->assertSame('files/9/passwd', S3Path::attachment(9, '../../etc/passwd'));
        $this->assertSame('files/9/file', S3Path::attachment(9, '...'));
    }
}

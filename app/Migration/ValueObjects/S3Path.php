<?php

namespace App\Migration\ValueObjects;

use Illuminate\Support\Str;

final class S3Path
{
    public static function body(int $emailId): string
    {
        return "emails/{$emailId}/body.html";
    }

    /**
     * Keyed by file, not by email: a files row shared by many emails maps to
     * exactly one S3 object, so it is uploaded once no matter how often it is
     * attached. Each email's file_s3_paths still records which key belongs to
     * which file_id.
     */
    public static function attachment(int $fileId, string $name): string
    {
        return "files/{$fileId}/".self::slugFilename($name);
    }

    private static function slugFilename(string $name): string
    {
        $extension = strtolower((string) preg_replace('/[^a-z0-9]/i', '', pathinfo($name, PATHINFO_EXTENSION)));
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME));

        if ($base === '') {
            $base = 'file';
        }

        return $extension !== '' ? "{$base}.{$extension}" : $base;
    }
}

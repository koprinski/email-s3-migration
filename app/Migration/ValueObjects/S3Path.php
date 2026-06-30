<?php

namespace App\Migration\ValueObjects;

use Illuminate\Support\Str;

final class S3Path
{
    public static function body(int $emailId): string
    {
        return "emails/{$emailId}/body.html";
    }

    public static function attachment(int $emailId, int $fileId, string $name): string
    {
        return "emails/{$emailId}/attachments/{$fileId}_".self::slugFilename($name);
    }

    /** Prefix covering every object for one email (handy for listing/cleanup). */
    public static function prefix(int $emailId): string
    {
        return "emails/{$emailId}/";
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

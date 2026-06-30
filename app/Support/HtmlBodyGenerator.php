<?php

namespace App\Support;

final class HtmlBodyGenerator
{
    private const PARAGRAPH = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, '
        .'sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad '
        .'minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea '
        .'commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit '
        .'esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat '
        .'non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. ';

    /**
     * Build an HTML body padded to at least $minBytes.
     */
    public static function make(int $minBytes = 10_240, int $variant = 0): string
    {
        $header = sprintf(
            "<!doctype html>\n<html lang=\"en\">\n<head><meta charset=\"utf-8\"><title>Notification #%d</title></head>\n<body>\n<h1>Account notification #%d</h1>\n",
            $variant,
            $variant,
        );
        $footer = "\n<hr>\n<footer><small>This is an automated message. Reference: "
            .str_pad((string) $variant, 8, '0', STR_PAD_LEFT)."</small></footer>\n</body>\n</html>\n";

        $html = $header;
        $i = 0;
        while (strlen($html) + strlen($footer) < $minBytes) {
            $html .= '<p class="para-'.$i.'">'.self::PARAGRAPH."</p>\n";
            $i++;
        }

        return $html.$footer;
    }
}

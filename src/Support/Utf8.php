<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Support;

/**
 * Byte offsets returned by PREG_OFFSET_CAPTURE must be converted to character
 * offsets, otherwise every reported column is wrong as soon as the text
 * contains a single accented character.
 */
final class Utf8
{
    private function __construct()
    {
    }

    public static function isAscii(string $text): bool
    {
        return \strlen($text) === mb_strlen($text);
    }

    public static function byteToCharOffset(string $subject, int $byteOffset, bool $ascii): int
    {
        if ($ascii) {
            return $byteOffset;
        }

        return mb_strlen(substr($subject, 0, $byteOffset));
    }

    public static function isValidUtf8(string $text): bool
    {
        return '' === $text || 1 === preg_match('//u', $text);
    }

    public static function excerpt(string $text, int $offset, int $length, int $radius = 32): string
    {
        $start = max(0, $offset - $radius);
        $excerpt = mb_substr($text, $start, $length + 2 * $radius);
        $excerpt = preg_replace('/\s+/u', ' ', $excerpt) ?? $excerpt;
        $excerpt = trim($excerpt);

        if ($start > 0) {
            $excerpt = '...'.$excerpt;
        }

        if ($offset + $length + $radius < mb_strlen($text)) {
            $excerpt .= '...';
        }

        return $excerpt;
    }
}

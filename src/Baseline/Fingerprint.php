<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Baseline;

use Acme\Spellcheck\Model\Misspelling;

/**
 * Stable identity of a misspelling.
 *
 * Deliberately excludes line, column and excerpt: adding a line above an issue
 * must not invalidate the baseline entry.
 */
final class Fingerprint
{
    public const SCHEMA_VERSION = 'v1';

    private function __construct()
    {
    }

    public static function of(Misspelling $misspelling): string
    {
        return self::compute(
            $misspelling->context->fingerprintSeed(),
            $misspelling->word,
            $misspelling->type->value,
        );
    }

    public static function compute(string $seed, string $word, string $type): string
    {
        return substr(hash('sha256', implode('|', [
            self::SCHEMA_VERSION,
            $seed,
            mb_strtolower($word),
            $type,
        ])), 0, 16);
    }
}

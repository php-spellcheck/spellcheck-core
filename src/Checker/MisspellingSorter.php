<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Checker;

use PHPSpellcheck\Core\Model\Misspelling;

/**
 * Deterministic ordering: path, line, column, word, context. Two identical
 * runs must produce byte identical output.
 */
final class MisspellingSorter
{
    private function __construct()
    {
    }

    /**
     * @return callable(Misspelling, Misspelling): int
     */
    public static function compare(): callable
    {
        return static function (Misspelling $a, Misspelling $b): int {
            return self::nullLast($a->location?->path, $b->location?->path)
                ?: self::nullLast($a->location?->line, $b->location?->line)
                ?: self::nullLast($a->location?->column, $b->location?->column)
                ?: strcmp($a->word, $b->word)
                ?: strcmp($a->context->fingerprintSeed(), $b->context->fingerprintSeed());
        };
    }

    /**
     * @param list<Misspelling> $misspellings
     *
     * @return list<Misspelling>
     */
    public static function sort(array $misspellings): array
    {
        usort($misspellings, self::compare());

        return $misspellings;
    }

    private static function nullLast(int|string|null $a, int|string|null $b): int
    {
        if ($a === $b) {
            return 0;
        }

        if (null === $a) {
            return 1;
        }

        if (null === $b) {
            return -1;
        }

        return \is_int($a) && \is_int($b) ? $a <=> $b : strcmp((string) $a, (string) $b);
    }
}

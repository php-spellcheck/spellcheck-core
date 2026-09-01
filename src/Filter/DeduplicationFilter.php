<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Filter;

use Acme\Spellcheck\Model\Misspelling;

/**
 * Collapses the same word reported twice at the same position, which happens
 * when a key equals its own translation or when a comment is attached to more
 * than one node.
 */
final class DeduplicationFilter implements MisspellingFilterInterface
{
    /** @var array<string, true> */
    private array $seen = [];

    public static function getDefaultPriority(): int
    {
        return 500;
    }

    public function filter(Misspelling $misspelling): ?Misspelling
    {
        $key = implode('|', [
            $misspelling->fingerprint(),
            $misspelling->location?->line ?? '',
            $misspelling->location?->column ?? '',
        ]);

        if (isset($this->seen[$key])) {
            return null;
        }

        $this->seen[$key] = true;

        return $misspelling;
    }

    public function reset(): void
    {
        $this->seen = [];
    }
}

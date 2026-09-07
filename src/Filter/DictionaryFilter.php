<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Filter;

use PHPSpellcheck\Core\Dictionary\DictionaryInterface;
use PHPSpellcheck\Core\Model\Misspelling;

/**
 * Applies the project dictionaries downstream of the backend, so that the
 * behaviour is identical whichever backend is in use and the dictionary
 * content is part of the cache key rather than of a temporary file.
 */
final class DictionaryFilter implements MisspellingFilterInterface
{
    public function __construct(
        private readonly DictionaryInterface $dictionary,
    ) {
    }

    public static function getDefaultPriority(): int
    {
        return 1000;
    }

    public function filter(Misspelling $misspelling): ?Misspelling
    {
        return $this->dictionary->contains($misspelling->word, $misspelling->language)
            ? null
            : $misspelling;
    }
}

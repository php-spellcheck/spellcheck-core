<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Filter;

use Acme\Spellcheck\Model\Misspelling;

final class PatternFilter implements MisspellingFilterInterface
{
    /**
     * @param list<string> $patterns full regular expressions, delimiters included
     */
    public function __construct(
        private readonly array $patterns = [],
    ) {
    }

    public static function getDefaultPriority(): int
    {
        return 900;
    }

    public function filter(Misspelling $misspelling): ?Misspelling
    {
        foreach ($this->patterns as $pattern) {
            if (1 === preg_match($pattern, $misspelling->word)) {
                return null;
            }
        }

        return $misspelling;
    }
}

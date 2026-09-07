<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Speller;

use PHPSpellcheck\Core\Model\MisspellingType;
use PHPSpellcheck\Core\Model\Word;

/**
 * Returned only for words the backend does not recognise.
 *
 * @psalm-immutable
 */
final class SpellerResult
{
    /**
     * @param list<string> $suggestions
     */
    public function __construct(
        public readonly Word $word,
        public readonly array $suggestions = [],
        public readonly MisspellingType $type = MisspellingType::SPELLING,
        public readonly string $language = '',
    ) {
    }

    public function withWord(Word $word): self
    {
        return new self($word, $this->suggestions, $this->type, $this->language);
    }

    /**
     * @param list<string> $suggestions
     */
    public function withSuggestions(array $suggestions): self
    {
        return new self($this->word, array_values($suggestions), $this->type, $this->language);
    }
}

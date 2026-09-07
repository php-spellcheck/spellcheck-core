<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Speller;

use PHPSpellcheck\Core\Model\Word;

interface SpellerInterface
{
    /**
     * @param iterable<Word> $words
     *
     * @return iterable<SpellerResult> only for the words that are NOT recognised
     */
    public function check(iterable $words, string $language, bool $withSuggestions = true): iterable;

    public function supportsLanguage(string $language): bool;

    /**
     * @return list<string>
     */
    public function getSupportedLanguages(): array;

    public function isAvailable(): bool;

    /**
     * Stable name used by the configuration and by spellcheck:doctor.
     */
    public function getName(): string;

    /**
     * Human readable version or detail, shown by spellcheck:doctor.
     */
    public function describe(): string;
}

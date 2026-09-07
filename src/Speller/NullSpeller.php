<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Speller;

/**
 * Accepts everything. Useful to measure the cost of the pipeline alone and as
 * an escape hatch in environments without any backend.
 */
final class NullSpeller implements SpellerInterface
{
    public function check(iterable $words, string $language, bool $withSuggestions = true): iterable
    {
        return [];
    }

    public function supportsLanguage(string $language): bool
    {
        return true;
    }

    public function getSupportedLanguages(): array
    {
        return [];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'null';
    }

    public function describe(): string
    {
        return 'no-op backend, every word is accepted';
    }
}

<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Speller;

use PHPSpellcheck\Core\Exception\UnsupportedLanguageException;
use PHPSpellcheck\Core\Model\MisspellingType;

/**
 * ext-pspell backend: no external process, but no word offsets either (which
 * we do not need, since we send one word at a time anyway).
 */
final class PspellSpeller implements SpellerInterface
{
    /** @var array<string, int> */
    private array $handles = [];

    public function __construct(
        private readonly int $mode = 0,
        private readonly int $maxSuggestions = 3,
    ) {
    }

    public function check(iterable $words, string $language, bool $withSuggestions = true): iterable
    {
        $handle = $this->handle($language);

        foreach ($words as $word) {
            if (pspell_check($handle, $word->value)) {
                continue;
            }

            $suggestions = [];

            if ($withSuggestions) {
                $suggestions = \array_slice(pspell_suggest($handle, $word->value) ?: [], 0, $this->maxSuggestions);
            }

            yield new SpellerResult($word, array_values($suggestions), MisspellingType::SPELLING, $language);
        }
    }

    public function supportsLanguage(string $language): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        try {
            $this->handle($language);
        } catch (UnsupportedLanguageException) {
            return false;
        }

        return true;
    }

    public function getSupportedLanguages(): array
    {
        // pspell exposes no way to enumerate the installed dictionaries.
        return [];
    }

    public function isAvailable(): bool
    {
        return \function_exists('pspell_new');
    }

    public function getName(): string
    {
        return 'pspell';
    }

    public function describe(): string
    {
        return $this->isAvailable()
            ? 'ext-pspell loaded (dictionary enumeration not supported)'
            : 'ext-pspell is not loaded';
    }

    private function handle(string $language): int
    {
        $language = str_replace('-', '_', $language);

        if (isset($this->handles[$language])) {
            return $this->handles[$language];
        }

        $handle = @pspell_new($language, '', '', 'utf-8', $this->mode);

        if (false === $handle) {
            throw UnsupportedLanguageException::create($language, 'pspell');
        }

        return $this->handles[$language] = $handle;
    }
}

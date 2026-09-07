<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Speller;

use PHPSpellcheck\Core\Dictionary\DictionaryInterface;
use PHPSpellcheck\Core\Model\MisspellingType;

/**
 * Pure PHP backend: a word list is the only source of truth. No external
 * binaries, which makes it the backend used by the unit tests and the fallback
 * when nothing else is installed.
 *
 * Suggestions come from a Levenshtein search restricted to the candidates
 * sharing the first letter and a similar length.
 */
final class WordListSpeller implements SpellerInterface
{
    /** @var array<string, list<string>>|null */
    private ?array $index = null;

    public function __construct(
        private readonly DictionaryInterface $dictionary,
        private readonly int $maxSuggestions = 3,
        private readonly int $maxDistance = 2,
    ) {
    }

    public function check(iterable $words, string $language, bool $withSuggestions = true): iterable
    {
        foreach ($words as $word) {
            if ($this->dictionary->contains($word->value, $language)) {
                continue;
            }

            // Compound words: accept them when every component is known.
            if ($this->isKnownCompound($word->value, $language)) {
                continue;
            }

            yield new SpellerResult(
                $word,
                $withSuggestions ? $this->suggest($word->value) : [],
                MisspellingType::SPELLING,
                $language,
            );
        }
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
        return 'wordlist';
    }

    public function describe(): string
    {
        return \sprintf('%d words (pure PHP, reduced coverage)', $this->dictionary->count());
    }

    private function isKnownCompound(string $word, string $language): bool
    {
        if (!preg_match('/[\'\-]/u', $word)) {
            return false;
        }

        $parts = preg_split('/[\'\-]/u', $word) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $p): bool => mb_strlen($p) > 1));

        if (\count($parts) < 2) {
            return false;
        }

        foreach ($parts as $part) {
            if (!$this->dictionary->contains($part, $language)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function suggest(string $word): array
    {
        if (0 === $this->maxSuggestions) {
            return [];
        }

        $index = $this->buildIndex();

        if ([] === $index) {
            return [];
        }

        $lower = mb_strtolower($word);
        $length = mb_strlen($lower);
        $first = mb_substr($lower, 0, 1);

        $candidates = [];

        for ($l = max(1, $length - $this->maxDistance); $l <= $length + $this->maxDistance; ++$l) {
            foreach ($index[$first.':'.$l] ?? [] as $candidate) {
                $distance = levenshtein($lower, $candidate);

                if ($distance <= $this->maxDistance) {
                    $candidates[$candidate] = $distance;
                }
            }
        }

        asort($candidates);

        return \array_slice(array_keys($candidates), 0, $this->maxSuggestions);
    }

    /**
     * @return array<string, list<string>>
     */
    private function buildIndex(): array
    {
        if (null !== $this->index) {
            return $this->index;
        }

        $index = [];

        if (method_exists($this->dictionary, 'getAllWords')) {
            /** @var list<string> $words */
            $words = $this->dictionary->getAllWords();
        } elseif (method_exists($this->dictionary, 'getWords')) {
            /** @var list<string> $words */
            $words = $this->dictionary->getWords();
        } else {
            $words = [];
        }

        foreach ($words as $candidate) {
            $lower = mb_strtolower($candidate);
            $index[mb_substr($lower, 0, 1).':'.mb_strlen($lower)][] = $lower;
        }

        return $this->index = $index;
    }
}

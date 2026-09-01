<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Dictionary;

/**
 * A flat list of accepted words, optionally scoped to one language.
 *
 * When case insensitive, every word is indexed lowercased. When case
 * sensitive, a capitalised entry ("Doctrine") also accepts the lowercase form
 * only if that form is listed explicitly.
 */
final class WordListDictionary implements DictionaryInterface
{
    /** @var array<string, true> */
    private array $words = [];

    private ?string $hash = null;

    /**
     * @param iterable<string> $words
     */
    public function __construct(
        iterable $words = [],
        private readonly ?string $language = null,
        private readonly bool $caseSensitive = false,
    ) {
        foreach ($words as $word) {
            $this->add($word);
        }
    }

    public function add(string $word): void
    {
        $word = trim($word);

        if ('' === $word) {
            return;
        }

        $this->words[$this->normalize($word)] = true;
        $this->hash = null;
    }

    public function contains(string $word, ?string $language = null): bool
    {
        if (null !== $this->language && null !== $language && !$this->matchesLanguage($language)) {
            return false;
        }

        return isset($this->words[$this->normalize($word)]);
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getVersionHash(): string
    {
        if (null === $this->hash) {
            $keys = array_keys($this->words);
            sort($keys);
            $this->hash = substr(hash('xxh128', ($this->language ?? '*').'|'.implode("\n", $keys)), 0, 16);
        }

        return $this->hash;
    }

    public function count(): int
    {
        return \count($this->words);
    }

    /**
     * @return list<string>
     */
    public function getWords(): array
    {
        return array_keys($this->words);
    }

    private function normalize(string $word): string
    {
        return $this->caseSensitive ? $word : mb_strtolower($word);
    }

    private function matchesLanguage(string $language): bool
    {
        $own = strtolower((string) $this->language);
        $other = strtolower($language);

        if ($own === $other) {
            return true;
        }

        // "it" matches "it_IT", and vice versa.
        return str_starts_with($other, $own.'_') || str_starts_with($own, $other.'_');
    }
}

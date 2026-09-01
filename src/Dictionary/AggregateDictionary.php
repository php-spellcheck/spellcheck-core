<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Dictionary;

final class AggregateDictionary implements DictionaryInterface
{
    /** @var list<DictionaryInterface> */
    private array $dictionaries;

    private ?string $hash = null;

    /**
     * @param iterable<DictionaryInterface> $dictionaries
     */
    public function __construct(iterable $dictionaries = [])
    {
        $this->dictionaries = $dictionaries instanceof \Traversable
            ? array_values(iterator_to_array($dictionaries, false))
            : array_values($dictionaries);
    }

    public function add(DictionaryInterface $dictionary): void
    {
        $this->dictionaries[] = $dictionary;
        $this->hash = null;
    }

    public function contains(string $word, ?string $language = null): bool
    {
        foreach ($this->dictionaries as $dictionary) {
            if ($dictionary->contains($word, $language)) {
                return true;
            }
        }

        return false;
    }

    public function getVersionHash(): string
    {
        if (null === $this->hash) {
            $parts = array_map(
                static fn (DictionaryInterface $d): string => $d->getVersionHash(),
                $this->dictionaries,
            );
            sort($parts);
            $this->hash = substr(hash('xxh128', implode('|', $parts)), 0, 16);
        }

        return $this->hash;
    }

    public function count(): int
    {
        $total = 0;

        foreach ($this->dictionaries as $dictionary) {
            $total += $dictionary->count();
        }

        return $total;
    }

    /**
     * @return list<string>
     */
    public function getAllWords(): array
    {
        $words = [];

        foreach ($this->dictionaries as $dictionary) {
            if ($dictionary instanceof WordListDictionary) {
                foreach ($dictionary->getWords() as $word) {
                    $words[$word] = true;
                }
            }
        }

        return array_keys($words);
    }
}

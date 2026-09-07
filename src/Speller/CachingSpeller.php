<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Speller;

use PHPSpellcheck\Core\Checker\RunStatisticsCollector;
use PHPSpellcheck\Core\Model\MisspellingType;
use PHPSpellcheck\Core\Model\Word;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Decorator adding two things the pipeline cannot live without:
 *
 *  - deduplication: the same word appearing 200 times is checked once;
 *  - persistence: correct words are cached as null, so that a warm run does
 *    not query the backend at all.
 */
final class CachingSpeller implements SpellerInterface
{
    public function __construct(
        private readonly SpellerInterface $inner,
        private readonly CacheItemPoolInterface $cache,
        private readonly string $configHash,
        private readonly int $ttl = 0,
        private readonly ?RunStatisticsCollector $statistics = null,
    ) {
    }

    public function check(iterable $words, string $language, bool $withSuggestions = true): iterable
    {
        /** @var array<string, list<Word>> $grouped */
        $grouped = [];

        foreach ($words as $word) {
            $grouped[$this->key($word->value, $language, $withSuggestions)][] = $word;
        }

        if ([] === $grouped) {
            return;
        }

        /** @var array<string, Word> $pending */
        $pending = [];

        foreach ($this->cache->getItems(array_keys($grouped)) as $key => $item) {
            if (!$item->isHit()) {
                $this->statistics?->cacheMiss();
                $pending[$key] = $grouped[$key][0];

                continue;
            }

            $this->statistics?->cacheHit();

            /** @var array{0: list<string>, 1: string}|null $hit */
            $hit = $item->get();

            if (null === $hit) {
                continue;
            }

            foreach ($grouped[$key] as $word) {
                yield new SpellerResult($word, $hit[0], MisspellingType::from($hit[1]), $language);
            }
        }

        if ([] === $pending) {
            return;
        }

        /** @var array<string, SpellerResult> $found keyed by cache key */
        $found = [];
        $byValue = [];

        foreach ($pending as $key => $word) {
            $byValue[mb_strtolower($word->value)] = $key;
        }

        foreach ($this->inner->check(array_values($pending), $language, $withSuggestions) as $result) {
            $key = $byValue[mb_strtolower($result->word->value)] ?? null;

            if (null !== $key) {
                $found[$key] = $result;
            }
        }

        foreach ($pending as $key => $word) {
            $result = $found[$key] ?? null;

            $item = $this->cache->getItem($key);
            $item->set(null === $result ? null : [$result->suggestions, $result->type->value]);

            if ($this->ttl > 0) {
                $item->expiresAfter($this->ttl);
            }

            $this->cache->saveDeferred($item);

            if (null === $result) {
                continue;
            }

            foreach ($grouped[$key] as $original) {
                yield new SpellerResult($original, $result->suggestions, $result->type, $language);
            }
        }

        $this->cache->commit();
    }

    public function supportsLanguage(string $language): bool
    {
        return $this->inner->supportsLanguage($language);
    }

    public function getSupportedLanguages(): array
    {
        return $this->inner->getSupportedLanguages();
    }

    public function isAvailable(): bool
    {
        return $this->inner->isAvailable();
    }

    public function getName(): string
    {
        return $this->inner->getName();
    }

    public function describe(): string
    {
        return $this->inner->describe().' (cached)';
    }

    public function getInner(): SpellerInterface
    {
        return $this->inner;
    }

    private function key(string $word, string $language, bool $withSuggestions): string
    {
        return 'sc_'.substr(hash('sha256', implode("\0", [
            $this->configHash,
            $language,
            $withSuggestions ? '1' : '0',
            mb_strtolower($word),
        ])), 0, 40);
    }
}

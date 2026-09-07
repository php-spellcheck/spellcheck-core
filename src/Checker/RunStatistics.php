<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Checker;

/**
 * @psalm-immutable
 */
final class RunStatistics
{
    /**
     * @param array<string, int> $perLanguage
     */
    public function __construct(
        public readonly int $fragments = 0,
        public readonly int $wordsChecked = 0,
        public readonly int $uniqueWordsChecked = 0,
        public readonly int $filesScanned = 0,
        public readonly int $suppressedByBaseline = 0,
        public readonly int $suppressedInline = 0,
        public readonly int $cacheHits = 0,
        public readonly int $cacheMisses = 0,
        public readonly float $durationSeconds = 0.0,
        public readonly array $perLanguage = [],
    ) {
    }

    public function cacheHitRate(): float
    {
        $total = $this->cacheHits + $this->cacheMisses;

        return 0 === $total ? 0.0 : $this->cacheHits / $total;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fragments' => $this->fragments,
            'files_scanned' => $this->filesScanned,
            'words_checked' => $this->wordsChecked,
            'unique_words_checked' => $this->uniqueWordsChecked,
            'suppressed_by_baseline' => $this->suppressedByBaseline,
            'suppressed_inline' => $this->suppressedInline,
            'cache_hits' => $this->cacheHits,
            'cache_misses' => $this->cacheMisses,
            'cache_hit_rate' => round($this->cacheHitRate(), 4),
            'duration_seconds' => round($this->durationSeconds, 3),
            'per_language' => $this->perLanguage,
        ];
    }
}

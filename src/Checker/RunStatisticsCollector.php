<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Checker;

/**
 * Mutable counter shared by the runner, the sources and the caching speller.
 * Long lived as a service: reset() is called at the start of every run.
 */
final class RunStatisticsCollector
{
    private int $fragments = 0;
    private int $words = 0;
    private int $files = 0;
    private int $suppressedBaseline = 0;
    private int $suppressedInline = 0;
    private int $cacheHits = 0;
    private int $cacheMisses = 0;

    /** @var array<string, true> */
    private array $uniqueWords = [];

    /** @var array<string, int> */
    private array $perLanguage = [];

    public function reset(): void
    {
        $this->fragments = 0;
        $this->words = 0;
        $this->files = 0;
        $this->suppressedBaseline = 0;
        $this->suppressedInline = 0;
        $this->cacheHits = 0;
        $this->cacheMisses = 0;
        $this->uniqueWords = [];
        $this->perLanguage = [];
    }

    public function fragment(): void
    {
        ++$this->fragments;
    }

    public function file(): void
    {
        ++$this->files;
    }

    public function word(string $value = '', string $language = ''): void
    {
        ++$this->words;

        if ('' !== $value) {
            $this->uniqueWords[$language.'|'.mb_strtolower($value)] = true;
        }

        if ('' !== $language) {
            $this->perLanguage[$language] = ($this->perLanguage[$language] ?? 0) + 1;
        }
    }

    public function suppressedByBaseline(): void
    {
        ++$this->suppressedBaseline;
    }

    public function suppressedInline(): void
    {
        ++$this->suppressedInline;
    }

    public function cacheHit(): void
    {
        ++$this->cacheHits;
    }

    public function cacheMiss(): void
    {
        ++$this->cacheMisses;
    }

    public function finish(float $duration): RunStatistics
    {
        return new RunStatistics(
            $this->fragments,
            $this->words,
            \count($this->uniqueWords),
            $this->files,
            $this->suppressedBaseline,
            $this->suppressedInline,
            $this->cacheHits,
            $this->cacheMisses,
            $duration,
            $this->perLanguage,
        );
    }
}

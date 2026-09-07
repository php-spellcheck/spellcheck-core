<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Filter;

use PHPSpellcheck\Core\Baseline\Baseline;
use PHPSpellcheck\Core\Checker\RunStatisticsCollector;
use PHPSpellcheck\Core\Model\Misspelling;

/**
 * Runs last, so that it only accounts for what would actually have been
 * reported.
 */
final class BaselineFilter implements MisspellingFilterInterface
{
    private ?Baseline $baseline = null;

    private bool $enabled = true;

    public function __construct(
        private readonly ?RunStatisticsCollector $statistics = null,
    ) {
    }

    public static function getDefaultPriority(): int
    {
        return 100;
    }

    public function setBaseline(?Baseline $baseline): void
    {
        $this->baseline = $baseline;
    }

    public function getBaseline(): ?Baseline
    {
        return $this->baseline;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function filter(Misspelling $misspelling): ?Misspelling
    {
        if (!$this->enabled || null === $this->baseline) {
            return $misspelling;
        }

        if ($this->baseline->contains($misspelling->fingerprint())) {
            $this->statistics?->suppressedByBaseline();

            return null;
        }

        return $misspelling;
    }
}

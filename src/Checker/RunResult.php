<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Checker;

use PHPSpellcheck\Core\Model\Diagnostic;
use PHPSpellcheck\Core\Model\Misspelling;

/**
 * @psalm-immutable
 */
final class RunResult
{
    /**
     * @param list<Misspelling> $misspellings
     * @param list<Diagnostic>  $diagnostics
     * @param list<string>      $outdatedBaselineEntries
     */
    public function __construct(
        public readonly array $misspellings,
        public readonly array $diagnostics,
        public readonly RunStatistics $stats,
        public readonly array $outdatedBaselineEntries = [],
    ) {
    }

    public function hasMisspellings(): bool
    {
        return [] !== $this->misspellings;
    }

    public function hasFatalDiagnostic(): bool
    {
        foreach ($this->diagnostics as $diagnostic) {
            if ($diagnostic->isFatal()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $fingerprints
     */
    public function withOutdated(array $fingerprints): self
    {
        return new self($this->misspellings, $this->diagnostics, $this->stats, array_values($fingerprints));
    }
}

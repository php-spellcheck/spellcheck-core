<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Diagnostics;

use PHPSpellcheck\Core\Model\Diagnostic;
use PHPSpellcheck\Core\Model\DiagnosticCode;
use PHPSpellcheck\Core\Model\Location;

/**
 * Shared, mutable sink for diagnostics. Long lived as a service: the runner
 * calls reset() at the beginning of every run.
 */
final class DiagnosticCollector
{
    /** @var list<Diagnostic> */
    private array $diagnostics = [];

    /** @var array<string, true> */
    private array $seen = [];

    public function add(DiagnosticCode $code, string $message, ?Location $location = null): void
    {
        $key = $code->value.'|'.$message.'|'.($location?->__toString() ?? '');

        if (isset($this->seen[$key])) {
            return;
        }

        $this->seen[$key] = true;
        $this->diagnostics[] = new Diagnostic($code, $message, $location);
    }

    /**
     * @return list<Diagnostic>
     */
    public function all(): array
    {
        return $this->diagnostics;
    }

    public function hasFatal(): bool
    {
        foreach ($this->diagnostics as $diagnostic) {
            if ($diagnostic->isFatal()) {
                return true;
            }
        }

        return false;
    }

    public function count(): int
    {
        return \count($this->diagnostics);
    }

    public function reset(): void
    {
        $this->diagnostics = [];
        $this->seen = [];
    }
}

<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Model;

/**
 * A non-spelling problem: skipped file, missing dictionary, malformed ICU.
 * Kept in a separate channel from misspellings so that the exit code can tell
 * warnings from errors.
 *
 * @psalm-immutable
 */
final class Diagnostic
{
    public function __construct(
        public readonly DiagnosticCode $code,
        public readonly string $message,
        public readonly ?Location $location = null,
    ) {
    }

    public function isFatal(): bool
    {
        return $this->code->isFatal();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code->value,
            'message' => $this->message,
            'location' => $this->location?->toArray(),
        ];
    }
}

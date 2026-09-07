<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Model;

use PHPSpellcheck\Core\Baseline\Fingerprint;

/**
 * @psalm-immutable
 */
final class Misspelling
{
    /**
     * @param list<string> $suggestions
     */
    public function __construct(
        public readonly string $word,
        public readonly MisspellingType $type,
        public readonly array $suggestions,
        public readonly string $language,
        public readonly FragmentContext $context,
        public readonly ?Location $location = null,
        public readonly string $excerpt = '',
        public readonly Severity $severity = Severity::ERROR,
    ) {
    }

    public function fingerprint(): string
    {
        return Fingerprint::of($this);
    }

    /**
     * @param list<string> $suggestions
     */
    public function withSuggestions(array $suggestions): self
    {
        return new self(
            $this->word,
            $this->type,
            array_values($suggestions),
            $this->language,
            $this->context,
            $this->location,
            $this->excerpt,
            $this->severity,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fingerprint' => $this->fingerprint(),
            'word' => $this->word,
            'type' => $this->type->value,
            'severity' => $this->severity->value,
            'language' => $this->language,
            'suggestions' => $this->suggestions,
            'excerpt' => $this->excerpt,
            'location' => $this->location?->toArray(),
            'context' => $this->context->toArray(),
        ];
    }
}

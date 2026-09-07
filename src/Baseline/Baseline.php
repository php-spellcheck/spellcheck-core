<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Baseline;

use PHPSpellcheck\Core\Model\Misspelling;

final class Baseline
{
    /**
     * @param array<string, array<string, mixed>> $entries fingerprint => payload
     */
    private function __construct(
        private array $entries = [],
        private readonly string $configHash = '',
        private array $used = [],
    ) {
    }

    public static function empty(string $configHash = ''): self
    {
        return new self([], $configHash);
    }

    /**
     * @param array<string, array<string, mixed>> $entries
     */
    public static function fromEntries(array $entries, string $configHash = ''): self
    {
        return new self($entries, $configHash);
    }

    /**
     * @param iterable<Misspelling> $misspellings
     */
    public static function fromMisspellings(iterable $misspellings, string $configHash = ''): self
    {
        $baseline = self::empty($configHash);

        foreach ($misspellings as $misspelling) {
            $baseline->record($misspelling);
        }

        return $baseline;
    }

    public function record(Misspelling $misspelling): void
    {
        $this->entries[$misspelling->fingerprint()] = [
            'word' => $misspelling->word,
            'type' => $misspelling->type->value,
            'language' => $misspelling->language,
            'context' => $misspelling->context->toArray(),
            'seen_at' => $misspelling->location?->path ?? $misspelling->location?->logical ?? '',
        ];
    }

    /**
     * Marks the fingerprint as reproduced during this run.
     */
    public function contains(string $fingerprint): bool
    {
        if (!isset($this->entries[$fingerprint])) {
            return false;
        }

        $this->used[$fingerprint] = true;

        return true;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Entries that are in the baseline but were not reproduced by this run.
     *
     * @return list<string>
     */
    public function outdated(): array
    {
        return array_values(array_diff(array_keys($this->entries), array_keys($this->used)));
    }

    public function withoutOutdated(): self
    {
        $entries = $this->entries;

        foreach ($this->outdated() as $fingerprint) {
            unset($entries[$fingerprint]);
        }

        return new self($entries, $this->configHash);
    }

    public function merge(self $other): self
    {
        return new self($other->entries + $this->entries, $this->configHash);
    }

    public function count(): int
    {
        return \count($this->entries);
    }

    public function configHash(): string
    {
        return $this->configHash;
    }

    public function isEmpty(): bool
    {
        return [] === $this->entries;
    }
}

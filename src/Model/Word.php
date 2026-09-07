<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Model;

/**
 * A candidate word, with its offset in the ORIGINAL text of the fragment.
 *
 * $id is assigned by the runner and is what correlates a SpellerResult back to
 * its fragment. Backends must carry it over unchanged.
 *
 * @psalm-immutable
 */
final class Word
{
    public function __construct(
        public readonly string $value,
        public readonly int $offset = 0,
        public readonly int $line = 1,
        public readonly ?string $parent = null,
        public readonly int $id = 0,
    ) {
    }

    public function withId(int $id): self
    {
        return new self($this->value, $this->offset, $this->line, $this->parent, $id);
    }

    public function lowercase(): string
    {
        return mb_strtolower($this->value);
    }

    public function length(): int
    {
        return mb_strlen($this->value);
    }
}

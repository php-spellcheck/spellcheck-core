<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Model;

/**
 * Resolves a character offset to a 1-based line number in O(log n).
 */
final class LineIndex
{
    /** @var list<int> character offsets of every newline */
    private array $newlines = [];

    public function __construct(string $text)
    {
        if (!str_contains($text, "\n")) {
            return;
        }

        $offset = 0;
        foreach (mb_str_split($text) as $char) {
            if ("\n" === $char) {
                $this->newlines[] = $offset;
            }
            ++$offset;
        }
    }

    public function lineAt(int $offset): int
    {
        if ([] === $this->newlines) {
            return 1;
        }

        $low = 0;
        $high = \count($this->newlines) - 1;
        $line = 1;

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);

            if ($this->newlines[$mid] < $offset) {
                $line = $mid + 2;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return $line;
    }

    public function columnAt(int $offset): int
    {
        $lineStart = 0;

        foreach ($this->newlines as $newline) {
            if ($newline >= $offset) {
                break;
            }
            $lineStart = $newline + 1;
        }

        return $offset - $lineStart + 1;
    }
}

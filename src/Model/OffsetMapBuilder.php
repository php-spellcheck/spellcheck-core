<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Model;

/**
 * Incrementally builds a transformed text together with its OffsetMap.
 *
 * Processors copy the parts they keep with keep() and replace what they remove
 * with emit(' '), so that words never get glued together.
 */
final class OffsetMapBuilder
{
    /** @var list<array{0:int,1:int,2:int}> */
    private array $segments = [];

    private string $text = '';

    private int $outOffset = 0;

    public function keep(string $chunk, int $originalOffset): void
    {
        if ('' === $chunk) {
            return;
        }

        $length = mb_strlen($chunk);
        $this->segments[] = [$this->outOffset, $originalOffset, $length];
        $this->outOffset += $length;
        $this->text .= $chunk;
    }

    public function emit(string $chunk = ' '): void
    {
        if ('' === $chunk) {
            return;
        }

        $this->outOffset += mb_strlen($chunk);
        $this->text .= $chunk;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getMap(): OffsetMap
    {
        return new OffsetMap($this->segments);
    }
}

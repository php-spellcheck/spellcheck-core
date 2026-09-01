<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Model;

/**
 * Maps offsets of a transformed text back to offsets of the original text.
 *
 * Every segment is [transformedOffset, originalOffset, length], expressed in
 * characters. A map without segments is the identity.
 *
 * @psalm-immutable
 */
final class OffsetMap
{
    /**
     * @param list<array{0:int,1:int,2:int}> $segments sorted by [0], non overlapping
     */
    public function __construct(private readonly array $segments = [])
    {
    }

    public static function identity(): self
    {
        return new self();
    }

    public function isIdentity(): bool
    {
        return [] === $this->segments;
    }

    /**
     * @return list<array{0:int,1:int,2:int}>
     */
    public function getSegments(): array
    {
        return $this->segments;
    }

    public function translate(int $transformedOffset): int
    {
        if ([] === $this->segments) {
            return $transformedOffset;
        }

        $segment = $this->findSegment($transformedOffset);

        if (null !== $segment) {
            return $segment[1] + ($transformedOffset - $segment[0]);
        }

        // The offset falls in a removed region: anchor it to the closest
        // defensible position, i.e. the end of the previous kept segment.
        $previous = $this->findPreviousSegment($transformedOffset);

        if (null === $previous) {
            return $this->segments[0][1];
        }

        return $previous[1] + $previous[2];
    }

    /**
     * $this maps t1 -> t0. $inner maps t2 -> t1. The result maps t2 -> t0.
     */
    public function compose(self $inner): self
    {
        if ($inner->isIdentity()) {
            return $this;
        }

        if ($this->isIdentity()) {
            return $inner;
        }

        $result = [];

        foreach ($inner->segments as [$outOffset, $midOffset, $length]) {
            $consumed = 0;

            while ($consumed < $length) {
                $segment = $this->findSegment($midOffset + $consumed);

                if (null === $segment) {
                    $result[] = [$outOffset + $consumed, $midOffset + $consumed, $length - $consumed];

                    break;
                }

                [$segOut, $segOrig, $segLen] = $segment;
                $delta = ($midOffset + $consumed) - $segOut;
                $take = min($length - $consumed, $segLen - $delta);

                $result[] = [$outOffset + $consumed, $segOrig + $delta, $take];
                $consumed += $take;
            }
        }

        return new self(self::normalize($result));
    }

    /**
     * Shifts every original offset by $delta. Used when a fragment is sliced
     * out of a bigger one.
     */
    public function shift(int $delta): self
    {
        if (0 === $delta || [] === $this->segments) {
            return $this;
        }

        $shifted = [];
        foreach ($this->segments as [$out, $orig, $length]) {
            $shifted[] = [$out, $orig + $delta, $length];
        }

        return new self($shifted);
    }

    /**
     * @return array{0:int,1:int,2:int}|null
     */
    private function findSegment(int $offset): ?array
    {
        $low = 0;
        $high = \count($this->segments) - 1;

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            [$start, , $length] = $this->segments[$mid];

            if ($offset < $start) {
                $high = $mid - 1;
            } elseif ($offset >= $start + $length) {
                $low = $mid + 1;
            } else {
                return $this->segments[$mid];
            }
        }

        return null;
    }

    /**
     * @return array{0:int,1:int,2:int}|null
     */
    private function findPreviousSegment(int $offset): ?array
    {
        $found = null;

        foreach ($this->segments as $segment) {
            if ($segment[0] + $segment[2] <= $offset) {
                $found = $segment;
            } else {
                break;
            }
        }

        return $found;
    }

    /**
     * @param list<array{0:int,1:int,2:int}> $segments
     *
     * @return list<array{0:int,1:int,2:int}>
     */
    private static function normalize(array $segments): array
    {
        usort($segments, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $merged = [];

        foreach ($segments as $segment) {
            if ($segment[2] <= 0) {
                continue;
            }

            $lastIndex = \count($merged) - 1;
            $last = $lastIndex >= 0 ? $merged[$lastIndex] : null;

            if (null !== $last
                && $last[0] + $last[2] === $segment[0]
                && $last[1] + $last[2] === $segment[1]
            ) {
                $merged[$lastIndex][2] += $segment[2];

                continue;
            }

            $merged[] = $segment;
        }

        return $merged;
    }
}

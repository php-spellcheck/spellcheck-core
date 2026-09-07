<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Filter;

use PHPSpellcheck\Core\Model\Misspelling;

final class FilterChain
{
    /** @var list<MisspellingFilterInterface> */
    private array $filters;

    /**
     * @param iterable<MisspellingFilterInterface> $filters
     */
    public function __construct(iterable $filters)
    {
        $filters = $filters instanceof \Traversable
            ? iterator_to_array($filters, false)
            : $filters;

        usort(
            $filters,
            static fn (MisspellingFilterInterface $a, MisspellingFilterInterface $b): int => $b::getDefaultPriority() <=> $a::getDefaultPriority(),
        );

        $this->filters = $filters;
    }

    public function filter(Misspelling $misspelling): ?Misspelling
    {
        $current = $misspelling;

        foreach ($this->filters as $filter) {
            $current = $filter->filter($current);

            if (null === $current) {
                return null;
            }
        }

        return $current;
    }

    public function reset(): void
    {
        foreach ($this->filters as $filter) {
            if (method_exists($filter, 'reset')) {
                $filter->reset();
            }
        }
    }
}

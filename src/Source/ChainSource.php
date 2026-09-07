<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Source;

final class ChainSource implements SourceInterface
{
    /** @var list<SourceInterface> */
    private array $sources;

    /**
     * @param iterable<SourceInterface> $sources
     */
    public function __construct(iterable $sources, private readonly string $name = 'chain')
    {
        $this->sources = $sources instanceof \Traversable
            ? array_values(iterator_to_array($sources, false))
            : array_values($sources);
    }

    public function fragments(): iterable
    {
        foreach ($this->sources as $source) {
            yield from $source->fragments();
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return list<SourceInterface>
     */
    public function getSources(): array
    {
        return $this->sources;
    }
}

<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Source;

use Acme\Spellcheck\Model\TextFragment;

/**
 * In memory source, used by the tests and by programmatic callers.
 */
final class ArrayFragmentSource implements SourceInterface
{
    /**
     * @param list<TextFragment> $fragments
     */
    public function __construct(
        private readonly array $fragments,
        private readonly string $name = 'array',
    ) {
    }

    public function fragments(): iterable
    {
        yield from $this->fragments;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

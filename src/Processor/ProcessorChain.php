<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Processor;

use PHPSpellcheck\Core\Model\TextFragment;

/**
 * Runs the processors in decreasing priority order, propagating the fragment
 * splits produced by the ICU and legacy plural processors.
 */
final class ProcessorChain
{
    /** @var list<TextProcessorInterface> */
    private array $processors;

    /**
     * @param iterable<TextProcessorInterface> $processors
     */
    public function __construct(iterable $processors)
    {
        $processors = $processors instanceof \Traversable
            ? iterator_to_array($processors, false)
            : $processors;

        usort(
            $processors,
            static fn (TextProcessorInterface $a, TextProcessorInterface $b): int => $b::getDefaultPriority() <=> $a::getDefaultPriority(),
        );

        $this->processors = $processors;
    }

    /**
     * @return list<TextFragment>
     */
    public function process(TextFragment $fragment): array
    {
        $current = [$fragment];

        foreach ($this->processors as $processor) {
            $next = [];

            foreach ($current as $item) {
                if (!$processor->supports($item)) {
                    $next[] = $item;

                    continue;
                }

                $result = $processor->process($item);

                if ($result instanceof TextFragment) {
                    $next[] = $result;

                    continue;
                }

                foreach ($result as $produced) {
                    $next[] = $produced;
                }
            }

            $current = $next;

            if ([] === $current) {
                return [];
            }
        }

        return array_values(array_filter(
            $current,
            static fn (TextFragment $item): bool => !$item->isBlank(),
        ));
    }

    /**
     * @return list<class-string<TextProcessorInterface>>
     */
    public function getProcessorNames(): array
    {
        return array_map(static fn (TextProcessorInterface $p): string => $p::class, $this->processors);
    }
}

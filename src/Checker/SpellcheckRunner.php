<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Checker;

use PHPSpellcheck\Core\Baseline\Baseline;
use PHPSpellcheck\Core\Diagnostics\DiagnosticCollector;
use PHPSpellcheck\Core\Filter\BaselineFilter;
use PHPSpellcheck\Core\Filter\FilterChain;
use PHPSpellcheck\Core\Model\Misspelling;
use PHPSpellcheck\Core\Model\TextFragment;
use PHPSpellcheck\Core\Model\Word;
use PHPSpellcheck\Core\Processor\ProcessorChain;
use PHPSpellcheck\Core\Source\SourceInterface;
use PHPSpellcheck\Core\Speller\SpellerInterface;
use PHPSpellcheck\Core\Tokenizer\TokenizerRegistry;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates the pipeline:
 *
 *   source -> processors -> tokenizer -> (batch per language) -> speller
 *          -> filters -> sorted result
 *
 * Everything is generator based, so memory stays flat regardless of project
 * size. Words are correlated back to their fragment through an explicit
 * integer id, never through object identity.
 */
final class SpellcheckRunner
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly ProcessorChain $processors,
        private readonly TokenizerRegistry $tokenizers,
        private readonly SpellerInterface $speller,
        private readonly FilterChain $filters,
        private readonly MisspellingFactory $factory,
        private readonly DiagnosticCollector $diagnostics,
        private readonly RunStatisticsCollector $statistics,
        private readonly ?BaselineFilter $baselineFilter = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param iterable<SourceInterface> $sources
     */
    public function run(iterable $sources, RunConfiguration $config, ?Baseline $baseline = null): RunResult
    {
        $started = microtime(true);

        $this->diagnostics->reset();
        $this->statistics->reset();
        $this->filters->reset();

        if (null !== $this->baselineFilter) {
            $this->baselineFilter->setEnabled($config->useBaseline);
            $this->baselineFilter->setBaseline($config->useBaseline ? $baseline : null);
        }

        /** @var list<Misspelling> $misspellings */
        $misspellings = [];

        /** @var array<string, list<Word>> $batches */
        $batches = [];
        /** @var array<int, TextFragment> $fragmentsById */
        $fragmentsById = [];
        $nextId = 0;

        foreach ($sources as $source) {
            $this->logger?->debug('Spellcheck source "{name}" started.', ['name' => $source->getName()]);

            foreach ($source->fragments() as $fragment) {
                $this->statistics->fragment();

                foreach ($this->processors->process($fragment) as $processed) {
                    $language = $config->resolveLanguage($processed->language);

                    if (null === $language) {
                        continue;
                    }

                    foreach ($this->tokenizers->tokenize($processed) as $word) {
                        $id = $nextId++;
                        $fragmentsById[$id] = $processed;
                        $batches[$language][] = $word->withId($id);

                        $this->statistics->word($word->value, $language);

                        if (\count($batches[$language]) >= self::BATCH_SIZE) {
                            foreach ($this->flush($language, $batches[$language], $fragmentsById, $config) as $misspelling) {
                                $misspellings[] = $misspelling;
                            }

                            // Only the ids of the flushed batch are released:
                            // other languages may still have pending words.
                            foreach ($batches[$language] as $flushed) {
                                unset($fragmentsById[$flushed->id]);
                            }

                            $batches[$language] = [];
                        }
                    }
                }
            }
        }

        foreach ($batches as $language => $pending) {
            if ([] === $pending) {
                continue;
            }

            foreach ($this->flush($language, $pending, $fragmentsById, $config) as $misspelling) {
                $misspellings[] = $misspelling;
            }
        }

        $result = new RunResult(
            MisspellingSorter::sort($misspellings),
            $this->diagnostics->all(),
            $this->statistics->finish(microtime(true) - $started),
            null !== $baseline && $config->useBaseline ? $baseline->outdated() : [],
        );

        $this->logger?->debug('Spellcheck finished: {count} issues.', ['count' => \count($result->misspellings)]);

        return $result;
    }

    /**
     * @param list<Word>               $batch
     * @param array<int, TextFragment> $fragmentsById
     *
     * @return iterable<Misspelling>
     */
    private function flush(string $language, array $batch, array $fragmentsById, RunConfiguration $config): iterable
    {
        foreach ($this->speller->check($batch, $language, $config->withSuggestions) as $result) {
            $fragment = $fragmentsById[$result->word->id] ?? null;

            if (null === $fragment) {
                continue;
            }

            $misspelling = $this->factory->create($result, $fragment);
            $filtered = $this->filters->filter($misspelling);

            if (null !== $filtered) {
                yield $filtered;
            }
        }
    }
}

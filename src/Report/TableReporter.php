<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Report;

use PHPSpellcheck\Core\Checker\RunResult;
use PHPSpellcheck\Core\Model\FragmentContext;
use PHPSpellcheck\Core\Model\Misspelling;

/**
 * Default human readable output, grouped by source and by file.
 *
 * Colours are emitted only when the writer says it is decorated, so piping to
 * a file produces clean text.
 */
final class TableReporter implements ReporterInterface
{
    private const RESET = "\033[0m";
    private const BOLD = "\033[1m";
    private const DIM = "\033[2m";
    private const RED = "\033[31m";
    private const YELLOW = "\033[33m";
    private const GREEN = "\033[32m";
    private const CYAN = "\033[36m";

    public function __construct(
        private readonly int $maxWordWidth = 24,
    ) {
    }

    public function getName(): string
    {
        return 'table';
    }

    public function report(RunResult $result, WriterInterface $writer): void
    {
        $groups = $this->group($result->misspellings);

        foreach ($groups as $sourceLabel => $files) {
            $writer->writeln('');
            $writer->writeln($this->style($writer, self::BOLD, $sourceLabel));
            $writer->writeln($this->style($writer, self::DIM, str_repeat('-', 76)));

            foreach ($files as $file => $misspellings) {
                $writer->writeln('');
                $writer->writeln('  '.$this->style($writer, self::CYAN, $file));

                foreach ($misspellings as $misspelling) {
                    $writer->writeln($this->line($writer, $misspelling));
                }
            }
        }

        $this->summary($result, $writer);
    }

    private function line(WriterInterface $writer, Misspelling $misspelling): string
    {
        $position = null !== $misspelling->location?->line
            ? \sprintf('%d:%d', $misspelling->location->line, $misspelling->location->column ?? 0)
            : '(line unknown)';

        $word = $this->pad($misspelling->word, $this->maxWordWidth);

        $suggestions = [] === $misspelling->suggestions
            ? $this->style($writer, self::DIM, '(no suggestion)')
            : '-> '.implode(', ', $misspelling->suggestions);

        $context = $this->contextLabel($misspelling);

        return \sprintf(
            '    %s  %s  %s  %s',
            $this->style($writer, self::DIM, $this->pad($position, 14)),
            $this->style($writer, self::RED, $word),
            $suggestions,
            '' === $context ? '' : $this->style($writer, self::DIM, $context),
        );
    }

    private function contextLabel(Misspelling $misspelling): string
    {
        if (FragmentContext::SOURCE_TRANSLATION === $misspelling->context->sourceType) {
            return (string) $misspelling->location?->logical;
        }

        $kind = $misspelling->context->get('kind') ?? '';
        $identifier = $misspelling->context->get('identifier') ?? '';

        if ('' === $kind) {
            return '';
        }

        return \in_array($kind, ['docblock', 'comment'], true)
            ? $kind
            : trim($kind.' '.$this->truncate($identifier, 40));
    }

    private function summary(RunResult $result, WriterInterface $writer): void
    {
        $stats = $result->stats;
        $count = \count($result->misspellings);

        $writer->writeln('');
        $writer->writeln($this->style($writer, self::DIM, str_repeat('-', 76)));

        $headline = 0 === $count
            ? $this->style($writer, self::GREEN, 'No new spelling issues.')
            : $this->style($writer, self::RED, \sprintf('%d new issue%s.', $count, 1 === $count ? '' : 's'));

        $writer->writeln('  '.$headline.\sprintf(
            ' %d suppressed by baseline, %d suppressed inline.',
            $stats->suppressedByBaseline,
            $stats->suppressedInline,
        ));

        $writer->writeln(\sprintf(
            '  %d files, %d fragments, %d words checked (%d unique), cache %d%% hit.',
            $stats->filesScanned,
            $stats->fragments,
            $stats->wordsChecked,
            $stats->uniqueWordsChecked,
            (int) round($stats->cacheHitRate() * 100),
        ));

        foreach ($result->diagnostics as $diagnostic) {
            $writer->writeln('  '.$this->style($writer, self::YELLOW, '! ').$diagnostic->message);
        }

        if ([] !== $result->outdatedBaselineEntries) {
            $writer->writeln(\sprintf(
                '  %s%d baseline entries were not reproduced; run "spellcheck:baseline --prune".',
                $this->style($writer, self::YELLOW, '! '),
                \count($result->outdatedBaselineEntries),
            ));
        }

        $writer->writeln(\sprintf('  %.2fs', $stats->durationSeconds));
        $writer->writeln('');
    }

    /**
     * @param list<Misspelling> $misspellings
     *
     * @return array<string, array<string, list<Misspelling>>>
     */
    private function group(array $misspellings): array
    {
        $groups = [];

        foreach ($misspellings as $misspelling) {
            $source = FragmentContext::SOURCE_TRANSLATION === $misspelling->context->sourceType
                ? 'Translations'
                : 'Code';

            $file = $misspelling->location->path
                ?? $misspelling->location->logical
                ?? '(unknown location)';

            $groups[$source][$file][] = $misspelling;
        }

        return $groups;
    }

    private function style(WriterInterface $writer, string $code, string $text): string
    {
        return $writer->isDecorated() ? $code.$text.self::RESET : $text;
    }

    private function pad(string $text, int $width): string
    {
        $length = mb_strlen($text);

        return $length >= $width ? $text : $text.str_repeat(' ', $width - $length);
    }

    private function truncate(string $text, int $width): string
    {
        return mb_strlen($text) <= $width ? $text : mb_substr($text, 0, $width - 1).'…';
    }
}

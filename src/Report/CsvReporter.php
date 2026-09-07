<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Report;

use PHPSpellcheck\Core\Checker\RunResult;

/**
 * Flat CSV meant for a human language reviewer.
 */
final class CsvReporter implements ReporterInterface
{
    private const HEADER = ['locale', 'domain', 'key', 'message', 'word', 'suggestions', 'file', 'line'];

    public function getName(): string
    {
        return 'csv';
    }

    public function report(RunResult $result, WriterInterface $writer): void
    {
        $writer->write($this->row(self::HEADER));

        foreach ($result->misspellings as $misspelling) {
            $writer->write($this->row([
                $misspelling->context->get('locale') ?? $misspelling->language,
                $misspelling->context->get('domain') ?? $misspelling->context->get('kind') ?? '',
                $misspelling->context->get('key') ?? $misspelling->context->get('identifier') ?? '',
                $misspelling->excerpt,
                $misspelling->word,
                implode(', ', $misspelling->suggestions),
                $misspelling->location?->path ?? '',
                (string) ($misspelling->location?->line ?? ''),
            ]));
        }
    }

    /**
     * @param list<string> $values
     */
    private function row(array $values): string
    {
        $escaped = array_map(
            static fn (string $value): string => '"'.str_replace('"', '""', str_replace(["\r\n", "\n", "\r"], ' ', $value)).'"',
            $values,
        );

        return implode(',', $escaped)."\n";
    }
}

<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Report;

use Acme\Spellcheck\Checker\RunResult;

final class JsonReporter implements ReporterInterface
{
    public function getName(): string
    {
        return 'json';
    }

    public function report(RunResult $result, WriterInterface $writer): void
    {
        $payload = [
            'summary' => [
                'new' => \count($result->misspellings),
            ] + $result->stats->toArray(),
            'issues' => array_map(
                static fn ($misspelling): array => $misspelling->toArray(),
                $result->misspellings,
            ),
            'diagnostics' => array_map(
                static fn ($diagnostic): array => $diagnostic->toArray(),
                $result->diagnostics,
            ),
            'outdated_baseline_entries' => $result->outdatedBaselineEntries,
        ];

        $json = json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        $writer->writeln(false === $json ? '{}' : $json);
    }
}

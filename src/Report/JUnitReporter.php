<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Report;

use PHPSpellcheck\Core\Checker\RunResult;

final class JUnitReporter implements ReporterInterface
{
    public function getName(): string
    {
        return 'junit';
    }

    public function report(RunResult $result, WriterInterface $writer): void
    {
        $writer->writeln('<?xml version="1.0" encoding="UTF-8"?>');
        $writer->writeln(sprintf(
            '<testsuites name="spellcheck" tests="%d" failures="%d" time="%.3f">',
            max(1, \count($result->misspellings)),
            \count($result->misspellings),
            $result->stats->durationSeconds,
        ));
        $writer->writeln('  <testsuite name="spelling">');

        if ([] === $result->misspellings) {
            $writer->writeln('    <testcase name="spelling" classname="spellcheck"/>');
        }

        foreach ($result->misspellings as $misspelling) {
            $writer->writeln(sprintf(
                '    <testcase name="%s" classname="%s">',
                self::attr($misspelling->word),
                self::attr($misspelling->location?->path ?? $misspelling->location?->logical ?? 'spellcheck'),
            ));
            $writer->writeln(sprintf(
                '      <failure type="spelling" message="%s">%s</failure>',
                self::attr(sprintf('Unknown word "%s"', $misspelling->word)),
                self::attr(sprintf(
                    "%s\nSuggestions: %s\nExcerpt: %s",
                    (string) $misspelling->location,
                    [] === $misspelling->suggestions ? '(none)' : implode(', ', $misspelling->suggestions),
                    $misspelling->excerpt,
                )),
            ));
            $writer->writeln('    </testcase>');
        }

        $writer->writeln('  </testsuite>');
        $writer->writeln('</testsuites>');
    }

    private static function attr(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_XML1, 'UTF-8');
    }
}

<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Report;

use PHPSpellcheck\Core\Checker\RunResult;
use PHPSpellcheck\Core\Model\Misspelling;

final class CheckstyleReporter implements ReporterInterface
{
    public function getName(): string
    {
        return 'checkstyle';
    }

    public function report(RunResult $result, WriterInterface $writer): void
    {
        /** @var array<string, list<Misspelling>> $byFile */
        $byFile = [];

        foreach ($result->misspellings as $misspelling) {
            $byFile[$misspelling->location?->path ?? $misspelling->location?->logical ?? 'unknown'][] = $misspelling;
        }

        $writer->writeln('<?xml version="1.0" encoding="UTF-8"?>');
        $writer->writeln('<checkstyle version="1.0">');

        foreach ($byFile as $file => $misspellings) {
            $writer->writeln(sprintf('  <file name="%s">', self::attr($file)));

            foreach ($misspellings as $misspelling) {
                $writer->writeln(sprintf(
                    '    <error line="%d" column="%d" severity="%s" message="%s" source="AcmeSpellcheck.Spelling"/>',
                    $misspelling->location?->line ?? 0,
                    $misspelling->location?->column ?? 0,
                    $misspelling->severity->value,
                    self::attr($this->message($misspelling)),
                ));
            }

            $writer->writeln('  </file>');
        }

        $writer->writeln('</checkstyle>');
    }

    private function message(Misspelling $misspelling): string
    {
        $message = sprintf('Unknown word "%s"', $misspelling->word);

        if ([] !== $misspelling->suggestions) {
            $message .= sprintf(' (%s)', implode(', ', $misspelling->suggestions));
        }

        return $message;
    }

    private static function attr(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_XML1, 'UTF-8');
    }
}

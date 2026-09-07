<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Report;

use PHPSpellcheck\Core\Checker\RunResult;

/**
 * GitLab Code Quality report. The fingerprint is deliberately the same as the
 * baseline one, so that GitLab deduplicates across pipelines exactly like the
 * baseline does across runs.
 */
final class GitlabReporter implements ReporterInterface
{
    public function getName(): string
    {
        return 'gitlab';
    }

    public function report(RunResult $result, WriterInterface $writer): void
    {
        $payload = [];

        foreach ($result->misspellings as $misspelling) {
            $description = \sprintf('Unknown word "%s"', $misspelling->word);

            if ([] !== $misspelling->suggestions) {
                $description .= \sprintf(' - did you mean %s?', implode(', ', $misspelling->suggestions));
            }

            $payload[] = [
                'description' => $description,
                'check_name' => 'spelling',
                'fingerprint' => $misspelling->fingerprint(),
                'severity' => 'minor',
                'location' => [
                    'path' => $misspelling->location->path ?? $misspelling->location->logical ?? 'unknown',
                    'lines' => ['begin' => $misspelling->location->line ?? 1],
                ],
            ];
        }

        $json = json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        $writer->writeln(false === $json ? '[]' : $json);
    }
}

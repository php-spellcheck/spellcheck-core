<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Report;

use PHPSpellcheck\Core\Checker\RunResult;
use PHPSpellcheck\Core\Model\Misspelling;

/**
 * GitHub Actions workflow commands.
 *
 * The message must be escaped: an unescaped "%" or newline truncates the
 * annotation.
 */
final class GithubReporter implements ReporterInterface
{
    public function getName(): string
    {
        return 'github';
    }

    public function report(RunResult $result, WriterInterface $writer): void
    {
        foreach ($result->misspellings as $misspelling) {
            $writer->writeln(\sprintf(
                '::error %s::%s',
                $this->parameters($misspelling),
                self::escape($this->message($misspelling)),
            ));
        }

        foreach ($result->diagnostics as $diagnostic) {
            $writer->writeln(\sprintf('::warning title=Spellcheck::%s', self::escape($diagnostic->message)));
        }
    }

    private function parameters(Misspelling $misspelling): string
    {
        $parameters = [];
        $location = $misspelling->location;

        if (null !== $location?->path) {
            $parameters['file'] = $location->path;
        }

        if (null !== $location?->line) {
            $parameters['line'] = (string) $location->line;
        }

        if (null !== $location?->column) {
            $parameters['col'] = (string) $location->column;
        }

        $parameters['title'] = 'Spelling';

        $parts = [];
        foreach ($parameters as $name => $value) {
            $parts[] = $name.'='.str_replace([',', "\n", "\r"], ['%2C', '', ''], $value);
        }

        return implode(',', $parts);
    }

    private function message(Misspelling $misspelling): string
    {
        $message = \sprintf('Unknown word "%s"', $misspelling->word);

        if ([] !== $misspelling->suggestions) {
            $message .= \sprintf(' - did you mean %s?', implode(', ', $misspelling->suggestions));
        }

        $domain = $misspelling->context->get('domain');

        if (null !== $domain) {
            $message .= \sprintf(' [%s/%s]', $misspelling->context->get('locale') ?? '?', $domain);
        }

        $identifier = $misspelling->context->get('identifier');

        if (null !== $identifier && $identifier !== $misspelling->word) {
            $message .= \sprintf(' [%s]', $identifier);
        }

        return $message;
    }

    public static function escape(string $value): string
    {
        return str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $value);
    }
}

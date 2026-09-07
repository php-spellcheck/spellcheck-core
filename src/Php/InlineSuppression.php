<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Php;

/**
 * Scans a PHP file for suppression markers:
 *
 *   @spellcheck-ignore-file
 *
 *   @spellcheck-ignore-line
 *
 *   @spellcheck-ignore-next-line
 *
 *   @spellcheck-disable ... @spellcheck-enable
 *
 *   @spellcheck-words foo bar
 */
final class InlineSuppression
{
    /** @var array<int, true> */
    private array $ignoredLines = [];

    private bool $wholeFile = false;

    /** @var list<string> */
    private array $extraWords = [];

    public static function scan(string $code, string $prefix = '@spellcheck'): self
    {
        $instance = new self();

        if (!str_contains($code, $prefix)) {
            return $instance;
        }

        $quoted = preg_quote($prefix, '/');
        $disabled = false;

        foreach (preg_split('/\R/', $code) ?: [] as $index => $line) {
            $lineNumber = $index + 1;

            if (1 === preg_match('/'.$quoted.'-ignore-file\b/', $line)) {
                $instance->wholeFile = true;

                return $instance;
            }

            if (1 === preg_match('/'.$quoted.'-disable\b/', $line)) {
                $disabled = true;
                $instance->ignoredLines[$lineNumber] = true;
            }

            if (1 === preg_match('/'.$quoted.'-enable\b/', $line)) {
                $disabled = false;
                $instance->ignoredLines[$lineNumber] = true;
            }

            if ($disabled) {
                $instance->ignoredLines[$lineNumber] = true;
            }

            if (1 === preg_match('/'.$quoted.'-ignore-line\b/', $line)) {
                $instance->ignoredLines[$lineNumber] = true;
            }

            if (1 === preg_match('/'.$quoted.'-ignore-next-line\b/', $line)) {
                $instance->ignoredLines[$lineNumber] = true;
                $instance->ignoredLines[$lineNumber + 1] = true;
            }

            if (1 === preg_match('/'.$quoted.'-words\s+(?P<words>[^*\/]+)/', $line, $matches)) {
                foreach (preg_split('/[\s,]+/', trim($matches['words'])) ?: [] as $word) {
                    if ('' !== $word) {
                        $instance->extraWords[] = $word;
                    }
                }
            }
        }

        return $instance;
    }

    public function isLineIgnored(int $line): bool
    {
        return $this->wholeFile || isset($this->ignoredLines[$line]);
    }

    public function coversWholeFile(): bool
    {
        return $this->wholeFile;
    }

    /**
     * @return list<string>
     */
    public function getExtraWords(): array
    {
        return $this->extraWords;
    }

    public function hasExtraWords(): bool
    {
        return [] !== $this->extraWords;
    }
}

<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Processor;

/**
 * Removes markdown code spans, fenced blocks, link targets and image
 * references, keeping the link label.
 */
final class MarkdownProcessor extends AbstractRegexProcessor
{
    private const PATTERN = '/
          ```.*?```                 # fenced block
        | `[^`]*`                   # code span
        | !\[[^\]]*\]\([^)]*\)      # image
        | \]\([^)]*\)               # link target
        | ^\s{4,}\S.*$              # indented code block
    /xums';

    public static function getDefaultPriority(): int
    {
        return 400;
    }

    protected function getPattern(): string
    {
        return self::PATTERN;
    }
}

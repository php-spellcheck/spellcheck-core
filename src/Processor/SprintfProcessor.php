<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Processor;

/**
 * Removes sprintf conversion specifications.
 */
final class SprintfProcessor extends AbstractRegexProcessor
{
    private const PATTERN = '/%(?:\d+\$)?[-+ 0#]?(?:\d+|\*)?(?:\.\d+)?[bcdeEfFgGosuxX]/u';

    public static function getDefaultPriority(): int
    {
        return 600;
    }

    protected function getPattern(): string
    {
        return self::PATTERN;
    }
}

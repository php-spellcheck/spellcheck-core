<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Processor;

/**
 * Removes HTML tags, comments and entities. strip_tags() is deliberately not
 * used: it would destroy the offsets.
 */
final class HtmlProcessor extends AbstractRegexProcessor
{
    /**
     * Alternatives: comment, tag, named entity, numeric entity.
     *
     * The delimiter is "~": both "/" and "#" appear inside the pattern, and PHP
     * scans for the closing delimiter before it knows about the x modifier, so
     * an unescaped occurrence would truncate the expression.
     */
    private const PATTERN = '~<!--.*?-->|</?[a-zA-Z][^<>]*>|&[a-zA-Z][a-zA-Z0-9]*;|&\#x?[0-9a-fA-F]+;~us';

    public static function getDefaultPriority(): int
    {
        return 500;
    }

    protected function getPattern(): string
    {
        return self::PATTERN;
    }
}

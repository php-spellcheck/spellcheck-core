<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Processor;

/**
 * Removes URLs, e-mail addresses and filesystem paths.
 */
final class UrlProcessor extends AbstractRegexProcessor
{
    /**
     * Alternatives: scheme://..., e-mail address, absolute filesystem path,
     * bare filename with a known extension.
     *
     * Delimited with "~" because "/" occurs throughout the pattern.
     */
    private const PATTERN = '~[a-z][a-z0-9+.\-]*://\S+'
        .'|(?:mailto:)?[\w.+\-]+@[\w\-]+(?:\.[\w\-]+)+'
        .'|(?:^|\s)/(?:[\w.\-]+/)+[\w.\-]*'
        .'|\b[\w\-]+\.(?:php|ya?ml|xml|json|twig|xlf|css|js|ts|md|lock|neon)\b~ui';

    public static function getDefaultPriority(): int
    {
        return 300;
    }

    protected function getPattern(): string
    {
        return self::PATTERN;
    }
}

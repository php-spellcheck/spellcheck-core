<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Processor;

/**
 * Removes Symfony, Twig, ICU-simple and named-parameter placeholders.
 */
final class PlaceholderProcessor extends AbstractRegexProcessor
{
    private const PATTERN = '/
          %[a-zA-Z0-9_.]+%            # %name%
        | %%                          # escaped percent
        | \{\{[^{}]*\}\}              # {{ var }}
        | \{[a-zA-Z0-9_.]*\}          # {name}, {0}, {}
        | (?<![:\w]):[a-zA-Z_]\w*     # :param
    /xu';

    public static function getDefaultPriority(): int
    {
        return 700;
    }

    protected function getPattern(): string
    {
        return self::PATTERN;
    }
}

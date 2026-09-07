<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core;

final class Version
{
    public const MAJOR = 1;
    public const MINOR = 0;
    public const PATCH = 0;
    public const EXTRA = 'dev';

    private function __construct()
    {
    }

    public static function string(): string
    {
        $version = \sprintf('%d.%d.%d', self::MAJOR, self::MINOR, self::PATCH);

        /** @phpstan-ignore-next-line notIdentical.alwaysTrue current EXTRA value is non-empty, but the check supports a future empty value */
        return '' !== self::EXTRA ? $version.'-'.self::EXTRA : $version;
    }
}

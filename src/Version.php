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
        $version = sprintf('%d.%d.%d', self::MAJOR, self::MINOR, self::PATCH);

        return '' !== self::EXTRA ? $version.'-'.self::EXTRA : $version;
    }
}

<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Dictionary;

use PHPSpellcheck\Core\Exception\InvalidArgumentException;

final class BuiltinDictionaries
{
    public const NAMES = ['technical', 'php', 'symfony'];

    private function __construct()
    {
    }

    public static function path(string $name): string
    {
        if (!\in_array($name, self::NAMES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown builtin dictionary "%s". Available: %s.',
                $name,
                implode(', ', self::NAMES),
            ));
        }

        return \dirname(__DIR__, 2).'/resources/dictionaries/'.$name.'.txt';
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    public static function paths(array $names): array
    {
        return array_map([self::class, 'path'], $names);
    }
}

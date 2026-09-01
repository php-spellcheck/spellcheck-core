<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Php;

use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * nikic/php-parser 4 and 5 expose different factory APIs. Isolating the
 * difference here keeps the rest of the code version agnostic.
 */
final class ParserFactoryCompat
{
    private function __construct()
    {
    }

    public static function create(): Parser
    {
        $factory = new ParserFactory();

        if (method_exists($factory, 'createForNewestSupportedVersion')) {
            /** @var Parser */
            return $factory->createForNewestSupportedVersion();
        }

        /** @phpstan-ignore-next-line php-parser 4 only */
        return $factory->create(ParserFactory::PREFER_PHP7);
    }
}

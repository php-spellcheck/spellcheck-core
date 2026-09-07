<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Php;

/**
 * Node classes renamed between php-parser 4 and 5.
 */
final class NodeClasses
{
    private function __construct()
    {
    }

    /**
     * @return class-string
     */
    public static function propertyItem(): string
    {
        /** @var class-string $class */
        $class = class_exists('PhpParser\\Node\\PropertyItem')
            ? 'PhpParser\\Node\\PropertyItem'
            : 'PhpParser\\Node\\Stmt\\PropertyProperty';

        return $class;
    }
}

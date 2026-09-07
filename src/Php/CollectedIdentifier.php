<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Php;

/**
 * @psalm-immutable
 */
final class CollectedIdentifier
{
    public function __construct(
        public readonly IdentifierKind $kind,
        public readonly string $value,
        public readonly int $line,
    ) {
    }
}

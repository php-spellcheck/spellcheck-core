<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Php;

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

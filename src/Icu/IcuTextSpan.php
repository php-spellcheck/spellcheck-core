<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Icu;

/**
 * A textual branch extracted from an ICU message, with its character offset in
 * the original message.
 *
 * @psalm-immutable
 */
final class IcuTextSpan
{
    public function __construct(
        public readonly string $text,
        public readonly int $offset,
    ) {
    }
}

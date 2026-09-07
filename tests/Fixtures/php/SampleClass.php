<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Tests\Fixtures\Php;

/**
 * A sample class with a deliberate typo in the docblock: an erorr.
 *
 * @see https://example.com/docs
 */
final class SampleClass
{
    public const MAX_RETRY_COUNT = 3;

    /**
     * @param string $recipientAdress the recipient postal adress
     */
    public function __construct(
        private readonly string $recipientAdress,
        private readonly int $atempts = 0,
    ) {
    }

    // @spellcheck-ignore-next-line
    public function deliberatelyMispelledMethod(): void
    {
    }

    public function getRecipientAdress(): string
    {
        return $this->recipientAdress;
    }
}

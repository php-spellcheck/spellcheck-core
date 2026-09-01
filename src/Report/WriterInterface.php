<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Report;

/**
 * Minimal output abstraction, so that the core does not depend on
 * symfony/console. The bundle provides a ConsoleWriter adapter.
 */
interface WriterInterface
{
    public function write(string $text): void;

    public function writeln(string $text = ''): void;

    public function isDecorated(): bool;
}

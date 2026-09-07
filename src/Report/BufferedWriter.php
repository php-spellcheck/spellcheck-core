<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Report;

final class BufferedWriter implements WriterInterface
{
    private string $buffer = '';

    public function __construct(private readonly bool $decorated = false)
    {
    }

    public function write(string $text): void
    {
        $this->buffer .= $text;
    }

    public function writeln(string $text = ''): void
    {
        $this->buffer .= $text."\n";
    }

    public function isDecorated(): bool
    {
        return $this->decorated;
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }

    public function reset(): void
    {
        $this->buffer = '';
    }
}

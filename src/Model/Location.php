<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Model;

/**
 * A physical location (file, line, column), a logical one
 * (locale/domain/key), or both.
 *
 * @psalm-immutable
 */
final class Location
{
    private function __construct(
        public readonly ?string $path = null,
        public readonly ?int $line = null,
        public readonly ?int $column = null,
        public readonly ?string $logical = null,
    ) {
    }

    public static function file(string $path, ?int $line = null, ?int $column = null): self
    {
        return new self($path, $line, $column);
    }

    public static function logical(string $descriptor): self
    {
        return new self(null, null, null, $descriptor);
    }

    public static function fileWithLogical(string $path, ?int $line, string $descriptor): self
    {
        return new self($path, $line, null, $descriptor);
    }

    public function withColumn(?int $column): self
    {
        return new self($this->path, $this->line, $column, $this->logical);
    }

    public function withLine(?int $line): self
    {
        return new self($this->path, $line, $this->column, $this->logical);
    }

    public function isPhysical(): bool
    {
        return null !== $this->path;
    }

    public function __toString(): string
    {
        if (null === $this->path) {
            return (string) $this->logical;
        }

        $out = $this->path;

        if (null !== $this->line) {
            $out .= ':'.$this->line;

            if (null !== $this->column) {
                $out .= ':'.$this->column;
            }
        }

        return $out;
    }

    /**
     * @return array{path: string|null, line: int|null, column: int|null, logical: string|null}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'line' => $this->line,
            'column' => $this->column,
            'logical' => $this->logical,
        ];
    }
}

<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Report;

use PHPSpellcheck\Core\Exception\InvalidArgumentException;

final class ReporterRegistry
{
    /** @var array<string, ReporterInterface> */
    private array $reporters = [];

    /**
     * @param iterable<ReporterInterface> $reporters
     */
    public function __construct(iterable $reporters = [])
    {
        foreach ($reporters as $reporter) {
            $this->reporters[$reporter->getName()] = $reporter;
        }
    }

    public function add(ReporterInterface $reporter): void
    {
        $this->reporters[$reporter->getName()] = $reporter;
    }

    public function get(string $name): ReporterInterface
    {
        if (!isset($this->reporters[$name])) {
            throw new InvalidArgumentException(\sprintf('Unknown report format "%s". Available formats: %s.', $name, implode(', ', $this->getNames())));
        }

        return $this->reporters[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->reporters[$name]);
    }

    /**
     * @return list<string>
     */
    public function getNames(): array
    {
        $names = array_keys($this->reporters);
        sort($names);

        return $names;
    }
}

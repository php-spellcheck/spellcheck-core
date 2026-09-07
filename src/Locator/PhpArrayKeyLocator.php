<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Locator;

/**
 * Line lookup in a PHP file returning an array of messages.
 */
final class PhpArrayKeyLocator implements KeyLocatorInterface
{
    /** @var array<string, array<string, int>> */
    private array $index = [];

    public function supports(string $path): bool
    {
        return 'php' === strtolower(pathinfo($path, \PATHINFO_EXTENSION));
    }

    public function locate(string $path, string $key, string $value): ?int
    {
        return $this->index($path)[$key] ?? null;
    }

    /**
     * @return array<string, int>
     */
    private function index(string $path): array
    {
        if (isset($this->index[$path])) {
            return $this->index[$path];
        }

        $contents = is_file($path) ? file_get_contents($path) : false;

        if (false === $contents) {
            return $this->index[$path] = [];
        }

        $index = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $offset => $line) {
            if (1 === preg_match('/([\'"])(?P<key>(?:\\\\.|(?!\1).)*)\1\s*=>/', $line, $m)) {
                $index[stripcslashes($m['key'])] ??= $offset + 1;
            }
        }

        return $this->index[$path] = $index;
    }
}

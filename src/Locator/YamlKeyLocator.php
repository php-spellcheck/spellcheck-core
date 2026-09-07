<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Locator;

/**
 * Best effort line lookup in a YAML translation file.
 *
 * Handles both flat files (keys containing dots) and nested files, by walking
 * the key segments while tracking indentation. Falls back to searching for the
 * value when the key is ambiguous. The whole file is indexed once, because
 * rescanning per message would be quadratic.
 */
final class YamlKeyLocator implements KeyLocatorInterface
{
    /** @var array<string, array<string, int>> path => key => line */
    private array $index = [];

    public function supports(string $path): bool
    {
        return \in_array(strtolower(pathinfo($path, \PATHINFO_EXTENSION)), ['yaml', 'yml'], true);
    }

    public function locate(string $path, string $key, string $value): ?int
    {
        $index = $this->index($path);

        if (isset($index[$key])) {
            return $index[$key];
        }

        // Fall back to the last segment, then to the literal value.
        $lastDot = strrpos($key, '.');

        if (false !== $lastDot) {
            $leaf = substr($key, $lastDot + 1);

            if (isset($index['#leaf:'.$leaf])) {
                return $index['#leaf:'.$leaf];
            }
        }

        return $index['#value:'.$value] ?? null;
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
        /** @var array<int, string> $stack indent => segment */
        $stack = [];
        /** @var array<string, int> $leafCount */
        $leafCount = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $offset => $line) {
            $lineNumber = $offset + 1;

            if ('' === trim($line) || 1 === preg_match('/^\s*#/', $line)) {
                continue;
            }

            if (1 !== preg_match('/^(?P<indent>\s*)(?P<quote>["\']?)(?P<key>[^:"\']+)(?P=quote)\s*:(?P<rest>.*)$/', $line, $m)) {
                continue;
            }

            $indent = \strlen($m['indent']);
            $segment = trim($m['key']);
            $rest = trim($m['rest']);

            foreach (array_keys($stack) as $level) {
                if ($level >= $indent) {
                    unset($stack[$level]);
                }
            }

            $stack[$indent] = $segment;
            ksort($stack);

            $fullKey = implode('.', $stack);

            if ('' !== $rest) {
                $index[$fullKey] = $lineNumber;

                $leafCount[$segment] = ($leafCount[$segment] ?? 0) + 1;
                $index['#leaf:'.$segment] = $lineNumber;

                $unquoted = trim($rest, "\"'");

                if ('' !== $unquoted) {
                    $index['#value:'.$unquoted] ??= $lineNumber;
                }
            }
        }

        // Ambiguous leaves are useless: drop them.
        foreach ($leafCount as $segment => $count) {
            if ($count > 1) {
                unset($index['#leaf:'.$segment]);
            }
        }

        return $this->index[$path] = $index;
    }
}

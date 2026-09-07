<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Locator;

/**
 * XLIFF line lookup.
 *
 * DOMDocument does not expose line numbers, so the file is scanned line by
 * line looking for the <source> element, the resname attribute or the unit id.
 */
final class XliffKeyLocator implements KeyLocatorInterface
{
    /** @var array<string, array<string, int>> */
    private array $index = [];

    public function supports(string $path): bool
    {
        return \in_array(strtolower(pathinfo($path, \PATHINFO_EXTENSION)), ['xlf', 'xliff'], true);
    }

    public function locate(string $path, string $key, string $value): ?int
    {
        $index = $this->index($path);

        return $index[$key] ?? $index['#value:'.$value] ?? null;
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
            $lineNumber = $offset + 1;

            if (1 === preg_match('#<source[^>]*>(?P<value>.*?)</source>#', $line, $m)) {
                $index[html_entity_decode($m['value'], \ENT_QUOTES | \ENT_XML1)] ??= $lineNumber;
            }

            if (1 === preg_match('/\bresname="(?P<value>[^"]+)"/', $line, $m)) {
                $index[html_entity_decode($m['value'], \ENT_QUOTES | \ENT_XML1)] ??= $lineNumber;
            }

            if (1 === preg_match('/<(?:trans-unit|unit)[^>]*\bid="(?P<value>[^"]+)"/', $line, $m)) {
                $index[html_entity_decode($m['value'], \ENT_QUOTES | \ENT_XML1)] ??= $lineNumber;
            }

            if (1 === preg_match('#<target[^>]*>(?P<value>.*?)</target>#', $line, $m)) {
                $index['#value:'.html_entity_decode($m['value'], \ENT_QUOTES | \ENT_XML1)] ??= $lineNumber;
            }
        }

        return $this->index[$path] = $index;
    }
}

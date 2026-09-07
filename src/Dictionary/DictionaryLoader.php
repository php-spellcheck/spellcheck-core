<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Dictionary;

use PHPSpellcheck\Core\Exception\InvalidArgumentException;

/**
 * Loads word list files.
 *
 * A file named "<name>.<locale>.txt" is scoped to that locale; any other name
 * applies to every language.
 */
final class DictionaryLoader
{
    public function __construct(
        private readonly bool $caseSensitive = false,
    ) {
    }

    public function load(string $path): WordListDictionary
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException(sprintf('The dictionary file "%s" does not exist or is not readable.', $path));
        }

        $contents = file_get_contents($path);

        if (false === $contents) {
            throw new InvalidArgumentException(sprintf('Unable to read the dictionary file "%s".', $path));
        }

        return new WordListDictionary(
            $this->parse($contents),
            $this->detectLanguage($path),
            $this->caseSensitive,
        );
    }

    /**
     * @param list<string> $paths
     */
    public function loadAll(array $paths): AggregateDictionary
    {
        $aggregate = new AggregateDictionary();

        foreach ($paths as $path) {
            $aggregate->add($this->load($path));
        }

        return $aggregate;
    }

    /**
     * @return list<string>
     */
    public function parse(string $contents): array
    {
        $words = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ('' === $line || str_starts_with($line, '#')) {
                continue;
            }

            // Support "word # comment" as well.
            $hash = strpos($line, ' #');
            if (false !== $hash) {
                $line = rtrim(substr($line, 0, $hash));
            }

            if ('' !== $line) {
                $words[] = $line;
            }
        }

        return $words;
    }

    public function detectLanguage(string $path): ?string
    {
        $basename = basename($path);

        if (1 === preg_match('/\.([a-z]{2,3}(?:_[A-Za-z0-9]{2,8})?)\.[a-z]+$/', $basename, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Rewrites a dictionary file sorted and deduplicated, preserving the
     * leading comment block.
     *
     * @param list<string> $words
     */
    public function save(string $path, array $words, string $header = ''): void
    {
        $words = array_values(array_unique(array_filter(array_map('trim', $words))));
        usort($words, static fn (string $a, string $b): int => strcasecmp($a, $b) ?: strcmp($a, $b));

        $contents = '' !== $header ? rtrim($header)."\n\n" : '';
        $contents .= implode("\n", $words)."\n";

        $directory = \dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new InvalidArgumentException(sprintf('Unable to create the directory "%s".', $directory));
        }

        file_put_contents($path, $contents);
    }
}

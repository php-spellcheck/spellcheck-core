<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Locator;

use Acme\Spellcheck\Model\Location;
use Symfony\Component\Finder\Finder;

/**
 * Resolves domain + locale + key to a file and, when possible, to a line.
 *
 * Used both by the file based translation source, which already knows the
 * file, and by the translator based one, which does not and has to look the
 * key up in the project translation paths.
 */
final class TranslationFileLocator
{
    /** @var list<KeyLocatorInterface> */
    private array $locators;

    /** @var array<string, list<string>>|null domain.locale => candidate files */
    private ?array $candidates = null;

    /**
     * @param list<string>                 $paths
     * @param iterable<KeyLocatorInterface> $locators
     */
    public function __construct(
        private readonly array $paths = [],
        iterable $locators = [],
        private readonly ?string $projectDir = null,
    ) {
        $locators = $locators instanceof \Traversable
            ? array_values(iterator_to_array($locators, false))
            : array_values($locators);

        $this->locators = [] !== $locators ? $locators : [
            new YamlKeyLocator(),
            new XliffKeyLocator(),
            new PhpArrayKeyLocator(),
        ];
    }

    public function locate(string $domain, string $locale, string $key, string $value): ?Location
    {
        foreach ($this->candidatesFor($domain, $locale) as $path) {
            $line = $this->lineIn($path, $key, $value);

            if (null !== $line) {
                return Location::fileWithLogical(
                    $this->relative($path),
                    $line,
                    $this->descriptor($locale, $domain, $key),
                );
            }
        }

        $first = $this->candidatesFor($domain, $locale)[0] ?? null;

        if (null !== $first) {
            return Location::fileWithLogical($this->relative($first), null, $this->descriptor($locale, $domain, $key));
        }

        return null;
    }

    public function locateInFile(string $path, string $domain, string $locale, string $key, string $value): Location
    {
        return Location::fileWithLogical(
            $this->relative($path),
            $this->lineIn($path, $key, $value),
            $this->descriptor($locale, $domain, $key),
        );
    }

    public function descriptor(string $locale, string $domain, string $key): string
    {
        return sprintf('%s/%s/%s', $locale, $domain, $key);
    }

    private function lineIn(string $path, string $key, string $value): ?int
    {
        foreach ($this->locators as $locator) {
            if ($locator->supports($path)) {
                return $locator->locate($path, $key, $value);
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function candidatesFor(string $domain, string $locale): array
    {
        $this->candidates ??= $this->buildCandidates();

        return $this->candidates[$domain.'.'.$locale] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    private function buildCandidates(): array
    {
        $directories = array_values(array_filter($this->paths, 'is_dir'));

        if ([] === $directories) {
            return [];
        }

        $candidates = [];

        $finder = (new Finder())->files()->in($directories)->sortByName();

        foreach ($finder as $file) {
            $basename = $file->getFilename();

            if (1 !== preg_match('/^(?P<domain>.+)\.(?P<locale>[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*(?:@[A-Za-z0-9]+)?)\.(?P<format>[a-z0-9]+)$/', $basename, $m)) {
                continue;
            }

            $domain = $m['domain'];
            $locale = str_replace('-', '_', $m['locale']);

            $candidates[$domain.'.'.$locale][] = $file->getPathname();

            // Also index without the +intl-icu suffix.
            if (str_ends_with($domain, '+intl-icu')) {
                $candidates[substr($domain, 0, -\strlen('+intl-icu')).'.'.$locale][] = $file->getPathname();
            }
        }

        return $candidates;
    }

    private function relative(string $path): string
    {
        if (null !== $this->projectDir && str_starts_with($path, $this->projectDir)) {
            return ltrim(substr($path, \strlen($this->projectDir)), \DIRECTORY_SEPARATOR);
        }

        return $path;
    }
}

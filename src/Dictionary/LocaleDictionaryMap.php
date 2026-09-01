<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Dictionary;

/**
 * Resolves an application locale ("it", "en", "pt_BR") to a system dictionary
 * name ("it_IT", "en_US", "pt_BR").
 */
final class LocaleDictionaryMap
{
    /**
     * @param array<string, string> $overrides locale => dictionary
     * @param list<string>          $available dictionaries installed on the system
     */
    public function __construct(
        private readonly array $overrides = [],
        private array $available = [],
    ) {
    }

    /**
     * @param list<string> $available
     */
    public function setAvailable(array $available): void
    {
        $this->available = $available;
    }

    public function resolve(string $locale): ?string
    {
        $locale = str_replace('-', '_', trim($locale));

        if (isset($this->overrides[$locale])) {
            return $this->overrides[$locale];
        }

        $language = strtok($locale, '_') ?: $locale;

        if (isset($this->overrides[$language])) {
            return $this->overrides[$language];
        }

        if ([] === $this->available) {
            // Nothing to check against: assume the locale is usable as is, and
            // fall back to the doubled form for two letter locales.
            return $locale === $language ? $language.'_'.strtoupper($language) : $locale;
        }

        if (\in_array($locale, $this->available, true)) {
            return $locale;
        }

        foreach ($this->available as $candidate) {
            if (str_starts_with($candidate, $language.'_')) {
                return $candidate;
            }
        }

        if (\in_array($language, $this->available, true)) {
            return $language;
        }

        return null;
    }
}

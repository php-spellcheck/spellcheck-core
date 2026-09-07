<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Checker;

use PHPSpellcheck\Core\Diagnostics\DiagnosticCollector;
use PHPSpellcheck\Core\Dictionary\LocaleDictionaryMap;
use PHPSpellcheck\Core\Model\DiagnosticCode;

/**
 * Effective configuration of a single run: the merge of the static
 * configuration and of the command line options.
 */
final class RunConfiguration
{
    /** @var array<string, string|null> */
    private array $languageCache = [];

    /**
     * @param list<string> $excludedLanguages
     */
    public function __construct(
        private readonly LocaleDictionaryMap $localeMap,
        public readonly bool $withSuggestions = true,
        public readonly int $maxSuggestions = 3,
        public readonly bool $useBaseline = true,
        public readonly bool $useCache = true,
        public readonly bool $failOnWarning = false,
        public readonly bool $ignoreWarnings = false,
        public readonly bool $reportOutdated = false,
        private readonly array $excludedLanguages = [],
        private readonly ?DiagnosticCollector $diagnostics = null,
    ) {
    }

    /**
     * Maps a fragment language onto a dictionary name, or null when the
     * language must be skipped.
     */
    public function resolveLanguage(string $language): ?string
    {
        if (\array_key_exists($language, $this->languageCache)) {
            return $this->languageCache[$language];
        }

        $base = strtok(str_replace('-', '_', $language), '_') ?: $language;

        if (\in_array($base, $this->excludedLanguages, true)) {
            $this->diagnostics?->add(
                DiagnosticCode::UNSUPPORTED_LANGUAGE,
                \sprintf('Language "%s" is excluded: word tokenization is not supported for it.', $language),
            );

            return $this->languageCache[$language] = null;
        }

        $resolved = $this->localeMap->resolve($language);

        if (null === $resolved) {
            $this->diagnostics?->add(
                DiagnosticCode::MISSING_DICTIONARY,
                \sprintf('No dictionary available for locale "%s"; the corresponding messages were skipped.', $language),
            );
        }

        return $this->languageCache[$language] = $resolved;
    }
}

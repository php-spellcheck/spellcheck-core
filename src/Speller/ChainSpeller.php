<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Speller;

use PHPSpellcheck\Core\Diagnostics\DiagnosticCollector;
use PHPSpellcheck\Core\Exception\SpellerNotAvailableException;
use PHPSpellcheck\Core\Model\DiagnosticCode;

/**
 * Implements "backend: auto": picks, at runtime, the first backend that is
 * available and knows the requested language.
 */
final class ChainSpeller implements SpellerInterface
{
    /** @var list<SpellerInterface> */
    private array $spellers;

    /** @var array<string, SpellerInterface> */
    private array $resolved = [];

    /**
     * @param iterable<SpellerInterface> $spellers ordered by preference
     */
    public function __construct(
        iterable $spellers,
        private readonly ?DiagnosticCollector $diagnostics = null,
    ) {
        $this->spellers = $spellers instanceof \Traversable
            ? array_values(iterator_to_array($spellers, false))
            : array_values($spellers);
    }

    public function check(iterable $words, string $language, bool $withSuggestions = true): iterable
    {
        return $this->pick($language)->check($words, $language, $withSuggestions);
    }

    public function supportsLanguage(string $language): bool
    {
        foreach ($this->spellers as $speller) {
            if ($speller->isAvailable() && $this->knowsLanguage($speller, $language)) {
                return true;
            }
        }

        return false;
    }

    public function getSupportedLanguages(): array
    {
        $languages = [];

        foreach ($this->spellers as $speller) {
            if (!$speller->isAvailable()) {
                continue;
            }

            foreach ($speller->getSupportedLanguages() as $language) {
                $languages[$language] = true;
            }
        }

        return array_keys($languages);
    }

    public function isAvailable(): bool
    {
        foreach ($this->spellers as $speller) {
            if ($speller->isAvailable()) {
                return true;
            }
        }

        return false;
    }

    public function getName(): string
    {
        return 'auto';
    }

    public function describe(): string
    {
        $parts = [];

        foreach ($this->spellers as $speller) {
            $parts[] = sprintf('%s: %s', $speller->getName(), $speller->isAvailable() ? 'available' : 'unavailable');
        }

        return implode(', ', $parts);
    }

    public function pick(string $language): SpellerInterface
    {
        if (isset($this->resolved[$language])) {
            return $this->resolved[$language];
        }

        $fallback = null;

        foreach ($this->spellers as $speller) {
            if (!$speller->isAvailable()) {
                continue;
            }

            if ($this->knowsLanguage($speller, $language)) {
                return $this->resolved[$language] = $speller;
            }

            $fallback ??= $speller;
        }

        if (null === $fallback) {
            throw new SpellerNotAvailableException(
                'No spell checking backend is available. Install hunspell or aspell, or configure '
                .'"backend: wordlist" together with at least one dictionary file.',
            );
        }

        $this->diagnostics?->add(
            DiagnosticCode::BACKEND_FALLBACK,
            sprintf('No backend declares a dictionary for "%s"; falling back to "%s".', $language, $fallback->getName()),
        );

        return $this->resolved[$language] = $fallback;
    }

    private function knowsLanguage(SpellerInterface $speller, string $language): bool
    {
        $supported = $speller->getSupportedLanguages();

        // A backend that cannot enumerate its dictionaries is asked directly.
        return [] === $supported
            ? $speller->supportsLanguage($language)
            : $speller->supportsLanguage($language);
    }
}

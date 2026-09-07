<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Speller;

/**
 * Rewrites a raw suggestion in the shape of the identifier it came from:
 *
 *   parent "getSuscriberList", word "Suscriber", suggestion "subscriber"
 *      -> "getSubscriberList"
 */
final class SuggestionFormatter
{
    /**
     * @param list<string> $suggestions
     *
     * @return list<string>
     */
    public function format(string $word, array $suggestions, ?string $parent, int $limit = 3): array
    {
        if (null === $parent || $parent === $word || '' === $parent) {
            return \array_slice($suggestions, 0, $limit);
        }

        $formatted = [];

        foreach ($suggestions as $suggestion) {
            if (str_contains($suggestion, ' ')) {
                // Multi word suggestions cannot be spliced into an identifier.
                continue;
            }

            $cased = $this->applyCase($word, $suggestion);
            $position = mb_strpos($parent, $word);

            if (false === $position) {
                $formatted[$cased] = true;

                continue;
            }

            $formatted[
                mb_substr($parent, 0, $position).$cased.mb_substr($parent, $position + mb_strlen($word))
            ] = true;
        }

        if ([] === $formatted) {
            return \array_slice($suggestions, 0, $limit);
        }

        return \array_slice(array_keys($formatted), 0, $limit);
    }

    private function applyCase(string $reference, string $suggestion): string
    {
        if ($reference === mb_strtoupper($reference) && mb_strlen($reference) > 1) {
            return mb_strtoupper($suggestion);
        }

        $first = mb_substr($reference, 0, 1);

        if ($first === mb_strtoupper($first) && $first !== mb_strtolower($first)) {
            return mb_strtoupper(mb_substr($suggestion, 0, 1)).mb_substr($suggestion, 1);
        }

        return mb_strtolower($suggestion);
    }
}

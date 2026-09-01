<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Checker;

use Acme\Spellcheck\Model\FragmentContext;
use Acme\Spellcheck\Model\LineIndex;
use Acme\Spellcheck\Model\Location;
use Acme\Spellcheck\Model\Misspelling;
use Acme\Spellcheck\Model\TextFragment;
use Acme\Spellcheck\Model\TokenizerMode;
use Acme\Spellcheck\Speller\SpellerResult;
use Acme\Spellcheck\Speller\SuggestionFormatter;
use Acme\Spellcheck\Support\Utf8;

/**
 * Turns a raw SpellerResult into a reportable Misspelling: resolves the
 * position in the original text, builds the excerpt and reshapes the
 * suggestions for identifiers.
 */
final class MisspellingFactory
{
    public function __construct(
        private readonly SuggestionFormatter $suggestionFormatter = new SuggestionFormatter(),
        private readonly int $maxSuggestions = 3,
    ) {
    }

    public function create(SpellerResult $result, TextFragment $fragment): Misspelling
    {
        $word = $result->word;
        $original = $fragment->getOriginalText();
        $index = new LineIndex($original);

        $line = $index->lineAt($word->offset);
        $column = $index->columnAt($word->offset);

        $location = $this->resolveLocation($fragment, $line, $column);

        $suggestions = TokenizerMode::IDENTIFIER === $fragment->tokenizer
            ? $this->suggestionFormatter->format($word->value, $result->suggestions, $word->parent, $this->maxSuggestions)
            : \array_slice($result->suggestions, 0, $this->maxSuggestions);

        return new Misspelling(
            $word->value,
            $result->type,
            array_values($suggestions),
            $result->language,
            $fragment->context,
            $location,
            Utf8::excerpt($original, $word->offset, $word->length()),
        );
    }

    private function resolveLocation(TextFragment $fragment, int $line, int $column): ?Location
    {
        $location = $fragment->location;

        if (null === $location) {
            return null;
        }

        if (FragmentContext::SOURCE_PHP === $fragment->context->sourceType) {
            // The offset is relative to the identifier or comment, not to the
            // file, so a column would be misleading. Multi line comments do
            // shift the line, though.
            return $location->withLine(($location->line ?? 1) + $line - 1);
        }

        // Translations: the column is the offset inside the message, 1 based,
        // which is what the requirement asks for.
        if (null !== $location->line && $line > 1) {
            return $location->withLine($location->line + $line - 1)->withColumn($column);
        }

        return $location->withColumn($column);
    }
}

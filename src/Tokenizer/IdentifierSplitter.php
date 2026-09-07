<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Tokenizer;

use PHPSpellcheck\Core\Model\TextFragment;
use PHPSpellcheck\Core\Model\TokenizerMode;
use PHPSpellcheck\Core\Model\Word;
use PHPSpellcheck\Core\Support\Utf8;

/**
 * Splits PHP identifiers into words.
 *
 *   getUserById        -> get, User, By, Id
 *   HTTPResponseCode   -> HTTP, Response, Code
 *   MAX_RETRY_COUNT    -> MAX, RETRY, COUNT
 *   utf8Encode         -> utf8, Encode
 */
final class IdentifierSplitter implements TokenizerInterface
{
    private const PATTERN = '/
          \p{Lu}+(?=\p{Lu}\p{Ll})    # acronym followed by a capitalised word
        | \p{Lu}?\p{Ll}+\d*          # word, optionally capitalised, digits glued
        | \p{Lu}+\d*                 # acronym or SCREAMING_SNAKE chunk
        | \d+                        # bare number
    /xu';

    /**
     * @param list<string> $ignorePatterns
     */
    public function __construct(
        private readonly int $minWordLength = 4,
        private readonly array $ignorePatterns = [],
    ) {
    }

    public function supports(TokenizerMode $mode): bool
    {
        return TokenizerMode::IDENTIFIER === $mode;
    }

    public function tokenize(TextFragment $fragment): iterable
    {
        $subject = $fragment->text;

        if (1 !== preg_match_all(self::PATTERN, $subject, $matches, \PREG_OFFSET_CAPTURE)
            && [] === $matches[0]
        ) {
            return;
        }

        $ascii = Utf8::isAscii($subject);
        $offsets = $fragment->getOffsets();

        foreach ($matches[0] as $match) {
            [$token, $byteOffset] = $match;

            if (!$this->isCandidate($token)) {
                continue;
            }

            yield new Word(
                $token,
                $offsets->translate(Utf8::byteToCharOffset($subject, $byteOffset, $ascii)),
                1,
                $subject,
            );
        }
    }

    /**
     * @return list<string>
     */
    public function split(string $identifier): array
    {
        preg_match_all(self::PATTERN, $identifier, $matches);

        return $matches[0];
    }

    private function isCandidate(string $token): bool
    {
        if (mb_strlen($token) < $this->minWordLength) {
            return false;
        }

        if (1 === preg_match('/^\d+$/', $token)) {
            return false;
        }

        // hex blobs and hashes
        if (1 === preg_match('/^[0-9a-f]{8,}$/i', $token)) {
            return false;
        }

        // long vowel-less tokens are almost never words
        if (mb_strlen($token) >= 20 && 1 !== preg_match('/[aeiouy]/iu', $token)) {
            return false;
        }

        foreach ($this->ignorePatterns as $pattern) {
            if (1 === preg_match($pattern, $token)) {
                return false;
            }
        }

        return true;
    }
}

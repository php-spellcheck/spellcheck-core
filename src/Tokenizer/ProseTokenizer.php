<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Tokenizer;

use PHPSpellcheck\Core\Model\LineIndex;
use PHPSpellcheck\Core\Model\TextFragment;
use PHPSpellcheck\Core\Model\TokenizerMode;
use PHPSpellcheck\Core\Model\Word;
use PHPSpellcheck\Core\Support\Utf8;

/**
 * Tokenizer for natural language.
 *
 * A word starts with a letter and may contain letters, combining marks, and
 * apostrophes or hyphens only when followed by another letter. This keeps
 * "dell'utente" and "e-mail" together while never emitting "--".
 */
final class ProseTokenizer implements TokenizerInterface
{
    private const WORD = '/\p{L}(?:[\p{L}\p{M}]|[\'\x{2019}\-](?=\p{L}))*/u';

    public function __construct(
        private readonly int $minWordLength = 4,
    ) {
    }

    public function supports(TokenizerMode $mode): bool
    {
        return \in_array($mode, [TokenizerMode::PROSE, TokenizerMode::DOCBLOCK], true);
    }

    public function tokenize(TextFragment $fragment): iterable
    {
        $subject = $fragment->text;

        preg_match_all(self::WORD, $subject, $matches, \PREG_OFFSET_CAPTURE);

        if ([] === $matches[0]) {
            return;
        }

        $ascii = Utf8::isAscii($subject);
        $offsets = $fragment->getOffsets();
        $lines = new LineIndex($subject);

        foreach ($matches[0] as $match) {
            [$token, $byteOffset] = $match;

            if (mb_strlen($token) < $this->minWordLength) {
                continue;
            }

            $charOffset = Utf8::byteToCharOffset($subject, $byteOffset, $ascii);

            yield new Word(
                $token,
                $offsets->translate($charOffset),
                $lines->lineAt($charOffset),
            );
        }
    }
}

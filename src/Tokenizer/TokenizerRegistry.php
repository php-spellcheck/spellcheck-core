<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Tokenizer;

use Acme\Spellcheck\Model\TextFragment;
use Acme\Spellcheck\Model\Word;

final class TokenizerRegistry
{
    /** @var list<TokenizerInterface> */
    private array $tokenizers;

    /**
     * @param iterable<TokenizerInterface> $tokenizers
     */
    public function __construct(iterable $tokenizers)
    {
        $this->tokenizers = $tokenizers instanceof \Traversable
            ? array_values(iterator_to_array($tokenizers, false))
            : array_values($tokenizers);
    }

    /**
     * @return iterable<Word>
     */
    public function tokenize(TextFragment $fragment): iterable
    {
        foreach ($this->tokenizers as $tokenizer) {
            if ($tokenizer->supports($fragment->tokenizer)) {
                yield from $tokenizer->tokenize($fragment);

                return;
            }
        }
    }
}

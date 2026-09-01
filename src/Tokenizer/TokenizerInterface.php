<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Tokenizer;

use Acme\Spellcheck\Model\TextFragment;
use Acme\Spellcheck\Model\TokenizerMode;
use Acme\Spellcheck\Model\Word;

interface TokenizerInterface
{
    /**
     * @return iterable<Word>
     */
    public function tokenize(TextFragment $fragment): iterable;

    public function supports(TokenizerMode $mode): bool;
}

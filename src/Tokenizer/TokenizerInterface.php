<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Tokenizer;

use PHPSpellcheck\Core\Model\TextFragment;
use PHPSpellcheck\Core\Model\TokenizerMode;
use PHPSpellcheck\Core\Model\Word;

interface TokenizerInterface
{
    /**
     * @return iterable<Word>
     */
    public function tokenize(TextFragment $fragment): iterable;

    public function supports(TokenizerMode $mode): bool;
}

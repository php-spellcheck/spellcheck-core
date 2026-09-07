<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Processor;

use PHPSpellcheck\Core\Model\TextFragment;

interface TextProcessorInterface
{
    /**
     * Returns the transformed fragment, or a list of fragments when the
     * processor splits the input (ICU branches, legacy plurals).
     *
     * @return TextFragment|list<TextFragment>
     */
    public function process(TextFragment $fragment): TextFragment|array;

    public function supports(TextFragment $fragment): bool;

    /**
     * Processors run in decreasing priority order.
     */
    public static function getDefaultPriority(): int;
}

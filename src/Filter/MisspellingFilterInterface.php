<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Filter;

use Acme\Spellcheck\Model\Misspelling;

interface MisspellingFilterInterface
{
    /**
     * Returns null to suppress the misspelling, or the (possibly modified)
     * misspelling to keep it.
     */
    public function filter(Misspelling $misspelling): ?Misspelling;

    /**
     * Filters run in decreasing priority order.
     */
    public static function getDefaultPriority(): int;
}

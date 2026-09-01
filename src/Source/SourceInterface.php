<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Source;

use Acme\Spellcheck\Model\TextFragment;

interface SourceInterface
{
    /**
     * @return iterable<TextFragment>
     */
    public function fragments(): iterable;

    /**
     * Stable name used by the CLI and the configuration.
     */
    public function getName(): string;
}

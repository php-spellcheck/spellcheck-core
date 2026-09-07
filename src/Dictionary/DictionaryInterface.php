<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Dictionary;

interface DictionaryInterface
{
    public function contains(string $word, ?string $language = null): bool;

    /**
     * Hash of the dictionary content: part of the cache key, so that adding a
     * word invalidates the cached results.
     */
    public function getVersionHash(): string;

    public function count(): int;
}

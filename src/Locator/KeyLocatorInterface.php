<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Locator;

interface KeyLocatorInterface
{
    public function supports(string $path): bool;

    /**
     * Returns the 1-based line of $key inside $path, or null when it cannot be
     * determined unambiguously.
     */
    public function locate(string $path, string $key, string $value): ?int;
}

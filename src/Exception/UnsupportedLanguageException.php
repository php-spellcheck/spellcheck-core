<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Exception;

final class UnsupportedLanguageException extends RuntimeException
{
    public static function create(string $language, string $backend): self
    {
        return new self(sprintf('The "%s" backend has no dictionary for language "%s".', $backend, $language));
    }
}

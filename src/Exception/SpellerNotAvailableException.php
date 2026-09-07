<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Exception;

final class SpellerNotAvailableException extends RuntimeException
{
    public static function forBackend(string $name, string $hint = ''): self
    {
        return new self(rtrim(sprintf('The "%s" speller backend is not available. %s', $name, $hint)));
    }
}

<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Model;

enum DiagnosticCode: string
{
    case PARSE_ERROR = 'parse_error';
    case MISSING_DICTIONARY = 'missing_dictionary';
    case MISSING_LOCALES = 'missing_locales';
    case SKIPPED_FILE = 'skipped_file';
    case ICU_SYNTAX = 'icu_syntax';
    case ENCODING = 'encoding';
    case UNSUPPORTED_LANGUAGE = 'unsupported_language';
    case BACKEND_FALLBACK = 'backend_fallback';
    case UNPARSABLE_FILENAME = 'unparsable_filename';
    case FATAL = 'fatal';

    public function isFatal(): bool
    {
        return self::FATAL === $this;
    }
}

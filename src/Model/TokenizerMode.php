<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Model;

enum TokenizerMode: string
{
    /** Natural language: catalogue messages, comments. */
    case PROSE = 'prose';

    /** camelCase / snake_case / SCREAMING_SNAKE identifiers. */
    case IDENTIFIER = 'identifier';

    /** Docblocks: prose, but with tags to strip first. */
    case DOCBLOCK = 'docblock';
}

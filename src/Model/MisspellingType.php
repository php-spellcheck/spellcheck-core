<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Model;

enum MisspellingType: string
{
    case SPELLING = 'spelling';
    case CASE_ = 'case';
}

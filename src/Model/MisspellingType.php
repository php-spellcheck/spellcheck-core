<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Model;

enum MisspellingType: string
{
    case SPELLING = 'spelling';
    case CASE_ = 'case';
}

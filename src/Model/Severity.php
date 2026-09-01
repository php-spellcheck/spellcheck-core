<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Model;

enum Severity: string
{
    case ERROR = 'error';
    case WARNING = 'warning';
}

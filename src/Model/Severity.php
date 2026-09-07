<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Model;

enum Severity: string
{
    case ERROR = 'error';
    case WARNING = 'warning';
}

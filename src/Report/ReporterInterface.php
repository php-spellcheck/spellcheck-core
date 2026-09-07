<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Report;

use PHPSpellcheck\Core\Checker\RunResult;

interface ReporterInterface
{
    public function report(RunResult $result, WriterInterface $writer): void;

    /**
     * Value accepted by the --format option.
     */
    public function getName(): string;
}

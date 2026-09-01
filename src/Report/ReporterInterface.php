<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Report;

use Acme\Spellcheck\Checker\RunResult;

interface ReporterInterface
{
    public function report(RunResult $result, WriterInterface $writer): void;

    /**
     * Value accepted by the --format option.
     */
    public function getName(): string;
}

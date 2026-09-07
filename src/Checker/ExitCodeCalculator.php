<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Checker;

final class ExitCodeCalculator
{
    public const SUCCESS = 0;
    public const ISSUES_FOUND = 1;
    public const ENVIRONMENT_ERROR = 2;
    public const WARNINGS_ONLY = 3;

    private function __construct()
    {
    }

    public static function calculate(RunResult $result, RunConfiguration $config): int
    {
        if ($result->hasFatalDiagnostic()) {
            return self::ENVIRONMENT_ERROR;
        }

        if ($result->hasMisspellings()) {
            return self::ISSUES_FOUND;
        }

        if ($config->reportOutdated && [] !== $result->outdatedBaselineEntries) {
            return self::ISSUES_FOUND;
        }

        if ([] === $result->diagnostics) {
            return self::SUCCESS;
        }

        if ($config->failOnWarning) {
            return self::ISSUES_FOUND;
        }

        if ($config->ignoreWarnings) {
            return self::SUCCESS;
        }

        return self::WARNINGS_ONLY;
    }
}

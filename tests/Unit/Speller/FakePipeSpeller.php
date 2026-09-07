<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Tests\Unit\Speller;

use PHPSpellcheck\Core\Speller\PipeSpeller;

/**
 * Drives PipeSpeller against tests/Fixtures/fake-speller.php.
 */
final class FakePipeSpeller extends PipeSpeller
{
    public function getName(): string
    {
        return 'fake';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function describe(): string
    {
        return 'fake speller';
    }

    protected function buildCommand(string $language): array
    {
        return [\PHP_BINARY, \dirname(__DIR__, 2).'/Fixtures/fake-speller.php', '--lang='.$language];
    }

    protected function detectLanguages(): array
    {
        return ['it_IT', 'en_US'];
    }
}

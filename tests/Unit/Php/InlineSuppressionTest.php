<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Tests\Unit\Php;

use PHPSpellcheck\Core\Php\InlineSuppression;
use PHPUnit\Framework\TestCase;

final class InlineSuppressionTest extends TestCase
{
    public function testIgnoreFile(): void
    {
        $suppression = InlineSuppression::scan("<?php\n// @spellcheck-ignore-file\nclass Foo {}\n");

        self::assertTrue($suppression->coversWholeFile());
        self::assertTrue($suppression->isLineIgnored(99));
    }

    public function testIgnoreNextLine(): void
    {
        $code = <<<'PHP'
            <?php
            // @spellcheck-ignore-next-line
            private string $mispelled = '';
            private string $other = '';
            PHP;

        $suppression = InlineSuppression::scan($code);

        self::assertTrue($suppression->isLineIgnored(3));
        self::assertFalse($suppression->isLineIgnored(4));
    }

    public function testIgnoreLine(): void
    {
        $code = "<?php\n\$a = 1; // @spellcheck-ignore-line\n\$b = 2;\n";
        $suppression = InlineSuppression::scan($code);

        self::assertTrue($suppression->isLineIgnored(2));
        self::assertFalse($suppression->isLineIgnored(3));
    }

    public function testDisableEnableBlock(): void
    {
        $code = <<<'PHP'
            <?php
            /* @spellcheck-disable */
            $a = 1;
            $b = 2;
            /* @spellcheck-enable */
            $c = 3;
            PHP;

        $suppression = InlineSuppression::scan($code);

        self::assertTrue($suppression->isLineIgnored(3));
        self::assertTrue($suppression->isLineIgnored(4));
        self::assertFalse($suppression->isLineIgnored(6));
    }

    public function testExtraWords(): void
    {
        $suppression = InlineSuppression::scan("<?php\n// @spellcheck-words Kbps Mbps, idempotency\n");

        self::assertSame(['Kbps', 'Mbps', 'idempotency'], $suppression->getExtraWords());
        self::assertTrue($suppression->hasExtraWords());
    }

    public function testCustomPrefix(): void
    {
        $suppression = InlineSuppression::scan("<?php\n// @nospell-ignore-file\n", '@nospell');

        self::assertTrue($suppression->coversWholeFile());
    }

    public function testFileWithoutMarkersIsCheap(): void
    {
        $suppression = InlineSuppression::scan("<?php\nclass Foo {}\n");

        self::assertFalse($suppression->coversWholeFile());
        self::assertFalse($suppression->isLineIgnored(1));
        self::assertSame([], $suppression->getExtraWords());
    }
}

<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Tests\Unit\Processor;

use PHPSpellcheck\Core\Model\FragmentContext;
use PHPSpellcheck\Core\Model\TextFragment;
use PHPSpellcheck\Core\Processor\PlaceholderProcessor;
use PHPUnit\Framework\TestCase;

final class PlaceholderProcessorTest extends TestCase
{
    /**
     * @dataProvider placeholderProvider
     */
    public function testPlaceholdersAreRemoved(string $input, string $expectedWithoutPlaceholders): void
    {
        $result = (new PlaceholderProcessor())->process($this->fragment($input));

        self::assertInstanceOf(TextFragment::class, $result);
        self::assertSame(
            $expectedWithoutPlaceholders,
            preg_replace('/\s+/', ' ', trim($result->text)),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function placeholderProvider(): iterable
    {
        yield 'symfony' => ['Ciao %name%, hai %count% messaggi', 'Ciao , hai messaggi'];
        yield 'twig' => ['Ciao {{ name }} bene', 'Ciao bene'];
        yield 'icu simple' => ['Ciao {name} bene', 'Ciao bene'];
        yield 'indexed' => ['Ciao {0} bene', 'Ciao bene'];
        yield 'named parameter' => ['where id = :userId here', 'where id = here'];
        yield 'escaped percent' => ['sconto del 20%% oggi', 'sconto del 20 oggi'];
    }

    public function testOffsetsPointAtTheOriginalText(): void
    {
        $original = 'Ciao %name%, hai %count% messagi';
        $result = (new PlaceholderProcessor())->process($this->fragment($original));

        self::assertInstanceOf(TextFragment::class, $result);

        $transformedOffset = mb_strpos($result->text, 'messagi');
        self::assertNotFalse($transformedOffset);

        self::assertSame(
            mb_strpos($original, 'messagi'),
            $result->getOffsets()->translate($transformedOffset),
        );
    }

    public function testUntouchedTextIsReturnedAsIs(): void
    {
        $fragment = $this->fragment('nothing to remove here');

        self::assertSame($fragment, (new PlaceholderProcessor())->process($fragment));
    }

    private function fragment(string $text): TextFragment
    {
        return new TextFragment($text, 'it', FragmentContext::translation('it', 'messages', 'k'));
    }
}

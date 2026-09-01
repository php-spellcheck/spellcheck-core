<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Tests\Unit\Icu;

use Acme\Spellcheck\Exception\IcuSyntaxException;
use Acme\Spellcheck\Icu\IcuMessageParser;
use Acme\Spellcheck\Icu\IcuTextSpan;
use PHPUnit\Framework\TestCase;

final class IcuMessageParserTest extends TestCase
{
    /**
     * @param list<string> $expected
     *
     * @dataProvider messageProvider
     */
    public function testParse(string $message, array $expected): void
    {
        self::assertSame($expected, $this->texts($message));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function messageProvider(): iterable
    {
        yield 'plain text' => ['Hello world', ['Hello world']];
        yield 'simple placeholder' => ['Ciao {name}', ['Ciao ']];
        yield 'placeholder in the middle' => ['a {x} b', ['a ', ' b']];
        yield 'plural' => [
            '{n, plural, one {Hai una mela} other {Hai # mele}}',
            ['Hai una mela', 'Hai ', ' mele'],
        ];
        yield 'select' => [
            '{g, select, female {Benvenuta} other {Benvenuto}}',
            ['Benvenuta', 'Benvenuto'],
        ];
        yield 'selectordinal' => [
            '{n, selectordinal, one {#st place} other {#th place}}',
            ['st place', 'th place'],
        ];
        yield 'plural with offset' => [
            '{n, plural, offset:1 one {uno} other {altri}}',
            ['uno', 'altri'],
        ];
        yield 'explicit selector' => [
            '{n, plural, =0 {vuoto} other {pieno}}',
            ['vuoto', 'pieno'],
        ];
        yield 'number style is opaque' => ['{v, number, ::currency/EUR} totale', [' totale']];
        yield 'date style is opaque' => ['{d, date, short} scadenza', [' scadenza']];
        yield 'doubled apostrophe' => ["l''utente", ["l'utente"]];
        yield 'plain apostrophe' => ["it's fine", ["it's fine"]];
        yield 'quoted braces are text' => ["usa '{'placeholder'}'", ['usa {placeholder}']];
        yield 'quoted hash is text' => ["'#' hash", ['# hash']];
        yield 'nested arguments' => [
            '{a, plural, one {{b, select, x {rosso} other {blu}}} other {molti}}',
            ['rosso', 'blu', 'molti'],
        ];
        yield 'hash outside plural is text' => ['issue #42 filed', ['issue #42 filed']];
        yield 'whitespace only branch is skipped' => ['{n, plural, one { } other {molti}}', ['molti']];
    }

    /**
     * @dataProvider malformedProvider
     */
    public function testMalformedMessagesThrow(string $message): void
    {
        $this->expectException(IcuSyntaxException::class);

        (new IcuMessageParser())->parse($message);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedProvider(): iterable
    {
        yield 'unterminated branch' => ['{n, plural, one {x'];
        yield 'stray closing brace' => ['hello }'];
        yield 'missing branch body' => ['{n, plural, one other {x}}'];
        yield 'unterminated style' => ['{v, number, ::currency'];
    }

    public function testSpanOffsetsPointAtTheOriginalMessage(): void
    {
        $message = '{n, plural, one {Hai una mela} other {altro}}';
        $spans = (new IcuMessageParser())->parse($message);

        self::assertSame(17, $spans[0]->offset);
        self::assertSame('Hai una mela', mb_substr($message, $spans[0]->offset, mb_strlen($spans[0]->text)));

        self::assertSame(38, $spans[1]->offset);
        self::assertSame('altro', mb_substr($message, $spans[1]->offset, mb_strlen($spans[1]->text)));
    }

    /**
     * @dataProvider looksLikeIcuProvider
     */
    public function testLooksLikeIcu(string $message, bool $expected): void
    {
        self::assertSame($expected, (new IcuMessageParser())->looksLikeIcu($message));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function looksLikeIcuProvider(): iterable
    {
        yield 'plural' => ['{n, plural, one {a} other {b}}', true];
        yield 'number' => ['{v, number, integer}', true];
        yield 'simple placeholder only' => ['Ciao {name}', false];
        yield 'plain text' => ['Ciao', false];
    }

    /**
     * @param list<IcuTextSpan> $spans
     *
     * @return list<string>
     */
    private function texts(string $message): array
    {
        return array_map(
            static fn (IcuTextSpan $span): string => $span->text,
            (new IcuMessageParser())->parse($message),
        );
    }
}

<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Tests\Unit\Tokenizer;

use Acme\Spellcheck\Model\FragmentContext;
use Acme\Spellcheck\Model\TextFragment;
use Acme\Spellcheck\Model\Word;
use Acme\Spellcheck\Tokenizer\ProseTokenizer;
use PHPUnit\Framework\TestCase;

final class ProseTokenizerTest extends TestCase
{
    /**
     * @param list<string> $expected
     *
     * @dataProvider proseProvider
     */
    public function testTokenize(string $text, array $expected): void
    {
        self::assertSame($expected, $this->words($text));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function proseProvider(): iterable
    {
        yield 'plain' => ['Inserisci il tuo indirizzo', ['Inserisci', 'indirizzo']];
        yield 'apostrophe kept' => ["dell'utente", ["dell'utente"]];
        yield 'hyphen kept' => ['anti-fraud checks', ['anti-fraud', 'checks']];
        yield 'dangling hyphen dropped' => ['word -- other', ['word', 'other']];
        yield 'accents' => ['città perché', ['città', 'perché']];
        yield 'digits are not words' => ['12345 abcd', ['abcd']];
    }

    public function testLineNumbersAreRelativeToTheFragment(): void
    {
        $fragment = new TextFragment(
            "first line here\nsecond line there",
            'en',
            FragmentContext::php('docblock', 'x'),
        );

        /** @var list<Word> $words */
        $words = iterator_to_array((new ProseTokenizer(4))->tokenize($fragment), false);

        self::assertSame(1, $words[0]->line);
        self::assertSame(2, $words[array_key_last($words)]->line);
    }

    /**
     * @return list<string>
     */
    private function words(string $text): array
    {
        $fragment = new TextFragment($text, 'it', FragmentContext::translation('it', 'messages', 'k'));

        return array_map(
            static fn (Word $word): string => $word->value,
            iterator_to_array((new ProseTokenizer(4))->tokenize($fragment), false),
        );
    }
}

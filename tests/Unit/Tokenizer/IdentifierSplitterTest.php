<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Tests\Unit\Tokenizer;

use PHPSpellcheck\Core\Model\FragmentContext;
use PHPSpellcheck\Core\Model\TextFragment;
use PHPSpellcheck\Core\Model\TokenizerMode;
use PHPSpellcheck\Core\Model\Word;
use PHPSpellcheck\Core\Tokenizer\IdentifierSplitter;
use PHPUnit\Framework\TestCase;

final class IdentifierSplitterTest extends TestCase
{
    /**
     * @param list<string> $expected
     *
     * @dataProvider splitProvider
     */
    public function testSplit(string $identifier, array $expected): void
    {
        self::assertSame($expected, (new IdentifierSplitter(1))->split($identifier));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function splitProvider(): iterable
    {
        yield 'camelCase' => ['getUserById', ['get', 'User', 'By', 'Id']];
        yield 'leading acronym' => ['HTTPResponseCode', ['HTTP', 'Response', 'Code']];
        yield 'snake_case' => ['first_name', ['first', 'name']];
        yield 'screaming snake' => ['MAX_RETRY_COUNT', ['MAX', 'RETRY', 'COUNT']];
        yield 'digits glued' => ['utf8Encode', ['utf8', 'Encode']];
        yield 'inner acronym' => ['DoctrineORMAdapter', ['Doctrine', 'ORM', 'Adapter']];
        yield 'two letter acronym' => ['IOException', ['IO', 'Exception']];
        yield 'magic method' => ['__invoke', ['invoke']];
        yield 'accented' => ['città', ['città']];
        yield 'version prefix' => ['v2Migration', ['v2', 'Migration']];
        yield 'mixed separators' => ['SomeClass_v1_2', ['Some', 'Class', 'v1', '2']];
        yield 'single letter' => ['a', ['a']];
    }

    public function testMinimumLengthFiltersShortTokens(): void
    {
        $words = $this->tokenize(new IdentifierSplitter(4), 'getUserById');

        self::assertSame(['User'], $words);
    }

    public function testNumericAndHexTokensAreDropped(): void
    {
        self::assertSame([], $this->tokenize(new IdentifierSplitter(1), '1234'));
        self::assertSame([], $this->tokenize(new IdentifierSplitter(1), 'deadbeefcafe'));
    }

    public function testIgnorePatternsAreApplied(): void
    {
        $splitter = new IdentifierSplitter(1, ['/^[A-Z]{2,5}$/']);

        self::assertSame(['Response', 'Code'], $this->tokenize($splitter, 'HTTPResponseCode'));
    }

    public function testOffsetsAreCharacterBasedOnMultibyteInput(): void
    {
        $fragment = new TextFragment(
            'cittàName',
            'en',
            FragmentContext::php('property', 'cittàName'),
            null,
            null,
            TokenizerMode::IDENTIFIER,
        );

        /** @var list<Word> $words */
        $words = iterator_to_array((new IdentifierSplitter(1))->tokenize($fragment), false);

        self::assertSame('città', $words[0]->value);
        self::assertSame(0, $words[0]->offset);
        self::assertSame('Name', $words[1]->value);
        self::assertSame(5, $words[1]->offset, 'the offset must be in characters, not bytes');
    }

    /**
     * @return list<string>
     */
    private function tokenize(IdentifierSplitter $splitter, string $identifier): array
    {
        $fragment = new TextFragment(
            $identifier,
            'en',
            FragmentContext::php('method', $identifier),
            null,
            null,
            TokenizerMode::IDENTIFIER,
        );

        return array_map(
            static fn (Word $word): string => $word->value,
            iterator_to_array($splitter->tokenize($fragment), false),
        );
    }
}

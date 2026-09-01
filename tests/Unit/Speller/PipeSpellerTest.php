<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Tests\Unit\Speller;

use Acme\Spellcheck\Exception\SpellerProcessException;
use Acme\Spellcheck\Model\Word;
use Acme\Spellcheck\Speller\SpellerResult;
use PHPUnit\Framework\TestCase;

final class PipeSpellerTest extends TestCase
{
    public function testCorrectWordsProduceNoResult(): void
    {
        $speller = new FakePipeSpeller('unused');

        $results = $this->check($speller, ['corretto', 'anche', 'questo']);

        self::assertSame([], $results);
    }

    public function testMisspelledWordCarriesSuggestions(): void
    {
        $speller = new FakePipeSpeller('unused');

        $results = $this->check($speller, ['mispell']);

        self::assertCount(1, $results);
        self::assertSame('mispell', $results[0]->word->value);
        self::assertSame(['misspell', 'misspelt'], $results[0]->suggestions);
    }

    public function testMisspelledWordWithoutSuggestions(): void
    {
        $speller = new FakePipeSpeller('unused');

        $results = $this->check($speller, ['nosuggestion']);

        self::assertCount(1, $results);
        self::assertSame([], $results[0]->suggestions);
    }

    public function testSuggestionsCanBeDisabled(): void
    {
        $speller = new FakePipeSpeller('unused');

        $results = iterator_to_array(
            $speller->check([new Word('mispell')], 'en_US', false),
            false,
        );

        self::assertSame([], $results[0]->suggestions);
    }

    public function testWordIdIsPreserved(): void
    {
        $speller = new FakePipeSpeller('unused');

        $results = iterator_to_array(
            $speller->check([new Word('mispell', 0, 1, null, 77)], 'en_US'),
            false,
        );

        self::assertSame(77, $results[0]->word->id);
    }

    public function testTheSameProcessIsReusedAcrossWords(): void
    {
        $speller = new FakePipeSpeller('unused');

        $first = $this->check($speller, ['mispell']);
        $second = $this->check($speller, ['messagi']);

        self::assertCount(1, $first);
        self::assertCount(1, $second);
    }

    public function testProcessDeathIsRecoveredOnce(): void
    {
        putenv('FAKE_SPELLER_DIE_AFTER=1');

        try {
            $speller = new FakePipeSpeller('unused');

            // The first word is answered, the second kills the process; the
            // speller restarts it once and completes the exchange.
            $this->check($speller, ['ok']);
            $results = $this->check($speller, ['mispell']);

            self::assertCount(1, $results);
        } finally {
            putenv('FAKE_SPELLER_DIE_AFTER');
        }
    }

    public function testAMissingBinaryIsReportedClearly(): void
    {
        $speller = new class('does-not-exist-anywhere') extends \Acme\Spellcheck\Speller\PipeSpeller {
            public function getName(): string
            {
                return 'broken';
            }

            public function isAvailable(): bool
            {
                return false;
            }

            public function describe(): string
            {
                return 'broken';
            }

            protected function buildCommand(string $language): array
            {
                return ['does-not-exist-anywhere', '-a'];
            }

            protected function detectLanguages(): array
            {
                return [];
            }
        };

        $this->expectException(SpellerProcessException::class);
        $this->expectExceptionMessageMatches('/did not emit its banner/');

        iterator_to_array($speller->check([new Word('word')], 'en_US'), false);
    }

    /**
     * @param list<string> $words
     *
     * @return list<SpellerResult>
     */
    private function check(FakePipeSpeller $speller, array $words): array
    {
        return array_values(iterator_to_array(
            $speller->check(
                array_map(static fn (string $word): Word => new Word($word), $words),
                'en_US',
            ),
            false,
        ));
    }
}

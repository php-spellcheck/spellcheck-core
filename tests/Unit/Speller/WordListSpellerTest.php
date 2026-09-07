<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Tests\Unit\Speller;

use PHPSpellcheck\Core\Dictionary\WordListDictionary;
use PHPSpellcheck\Core\Model\Word;
use PHPSpellcheck\Core\Speller\SpellerResult;
use PHPSpellcheck\Core\Speller\WordListSpeller;
use PHPUnit\Framework\TestCase;

final class WordListSpellerTest extends TestCase
{
    public function testKnownWordsAreAccepted(): void
    {
        $speller = $this->speller(['indirizzo', 'spedizione']);

        self::assertSame([], $this->check($speller, ['indirizzo', 'Indirizzo', 'SPEDIZIONE']));
    }

    public function testUnknownWordIsReportedWithSuggestions(): void
    {
        $speller = $this->speller(['indirizzo', 'indirizzi', 'spedizione']);

        $results = $this->check($speller, ['indirizio']);

        self::assertCount(1, $results);
        self::assertSame(['indirizzo', 'indirizzi'], $results[0]->suggestions);
    }

    public function testSuggestionsCanBeDisabled(): void
    {
        $speller = $this->speller(['indirizzo']);

        $results = iterator_to_array($speller->check([new Word('indirizio')], 'it', false), false);

        self::assertSame([], $results[0]->suggestions);
    }

    public function testCompoundWordIsAcceptedWhenEveryPartIsKnown(): void
    {
        $speller = $this->speller(['anti', 'fraud', 'dell', 'utente']);

        self::assertSame([], $this->check($speller, ['anti-fraud', "dell'utente"]));
    }

    public function testCompoundWordIsRejectedWhenAPartIsUnknown(): void
    {
        $speller = $this->speller(['anti']);

        self::assertCount(1, $this->check($speller, ['anti-fraudd']));
    }

    public function testIsAlwaysAvailable(): void
    {
        self::assertTrue($this->speller([])->isAvailable());
        self::assertSame('wordlist', $this->speller([])->getName());
    }

    /**
     * @param list<string> $words
     */
    private function speller(array $words): WordListSpeller
    {
        return new WordListSpeller(new WordListDictionary($words), 3);
    }

    /**
     * @param list<string> $words
     *
     * @return list<SpellerResult>
     */
    private function check(WordListSpeller $speller, array $words): array
    {
        return array_values(iterator_to_array(
            $speller->check(array_map(static fn (string $w): Word => new Word($w), $words), 'it'),
            false,
        ));
    }
}

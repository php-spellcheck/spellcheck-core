<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Tests\Integration;

use PHPSpellcheck\Core\Model\Word;
use PHPSpellcheck\Core\Speller\HunspellSpeller;
use PHPSpellcheck\Core\Speller\SpellerResult;
use PHPUnit\Framework\TestCase;

/**
 * Runs against the real binary. Skipped unless hunspell and the it_IT and
 * en_US dictionaries are installed.
 *
 * @group integration
 */
final class HunspellSpellerTest extends TestCase
{
    private HunspellSpeller $speller;

    protected function setUp(): void
    {
        $this->speller = new HunspellSpeller();

        if (!$this->speller->isAvailable()) {
            self::markTestSkipped('hunspell is not installed.');
        }
    }

    protected function tearDown(): void
    {
        $this->speller->stop();
    }

    public function testItalianDictionary(): void
    {
        $this->requireLanguage('it_IT');

        $results = $this->check(['indirizzo', 'indirizio', 'spedizione']);

        self::assertSame(['indirizio'], array_map(static fn (SpellerResult $r): string => $r->word->value, $results));
        self::assertNotEmpty($results[0]->suggestions);
    }

    public function testEnglishDictionary(): void
    {
        $this->requireLanguage('en_US');

        $results = $this->check(['subscriber', 'suscriber'], 'en_US');

        self::assertCount(1, $results);
        self::assertContains('subscriber', $results[0]->suggestions);
    }

    /**
     * The whole point of the pipe protocol: one process for the whole run.
     */
    public function testManyWordsGoThroughASingleProcess(): void
    {
        $this->requireLanguage('it_IT');

        $words = [];
        for ($i = 0; $i < 500; ++$i) {
            $words[] = 0 === $i % 100 ? 'indirizio' : 'indirizzo';
        }

        $results = $this->check($words);

        self::assertCount(5, $results);
    }

    /**
     * Terse mode must not break the synchronisation: the backend still has to
     * emit the terminating blank line. If this test hangs or times out on some
     * distribution, terse_mode must default to false there.
     */
    public function testTerseModeKeepsTheProtocolInSync(): void
    {
        $this->requireLanguage('it_IT');

        $terse = new HunspellSpeller('hunspell', [], 10.0, true);
        $verbose = new HunspellSpeller('hunspell', [], 10.0, false);

        try {
            $words = ['indirizzo', 'indirizio', 'spedizione', 'messagi'];

            self::assertSame(
                $this->extract($terse->check($this->words($words), 'it_IT')),
                $this->extract($verbose->check($this->words($words), 'it_IT')),
            );
        } finally {
            $terse->stop();
            $verbose->stop();
        }
    }

    public function testSupportedLanguagesAreEnumerated(): void
    {
        self::assertNotEmpty($this->speller->getSupportedLanguages());
    }

    public function testDescribeReportsTheVersion(): void
    {
        self::assertNotSame('', $this->speller->describe());
    }

    private function requireLanguage(string $language): void
    {
        if (!$this->speller->supportsLanguage($language)) {
            self::markTestSkipped(sprintf('The %s hunspell dictionary is not installed.', $language));
        }
    }

    /**
     * @param list<string> $words
     *
     * @return list<SpellerResult>
     */
    private function check(array $words, string $language = 'it_IT'): array
    {
        return array_values(iterator_to_array($this->speller->check($this->words($words), $language), false));
    }

    /**
     * @param list<string> $words
     *
     * @return list<Word>
     */
    private function words(array $words): array
    {
        return array_map(static fn (string $word): Word => new Word($word), $words);
    }

    /**
     * @param iterable<SpellerResult> $results
     *
     * @return list<string>
     */
    private function extract(iterable $results): array
    {
        $words = [];

        foreach ($results as $result) {
            $words[] = $result->word->value;
        }

        return $words;
    }
}

<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Tests\Unit\Checker;

use Acme\Spellcheck\Baseline\Baseline;
use Acme\Spellcheck\Checker\ExitCodeCalculator;
use Acme\Spellcheck\Checker\MisspellingFactory;
use Acme\Spellcheck\Checker\RunConfiguration;
use Acme\Spellcheck\Checker\RunStatisticsCollector;
use Acme\Spellcheck\Checker\SpellcheckRunner;
use Acme\Spellcheck\Diagnostics\DiagnosticCollector;
use Acme\Spellcheck\Dictionary\AggregateDictionary;
use Acme\Spellcheck\Dictionary\LocaleDictionaryMap;
use Acme\Spellcheck\Dictionary\WordListDictionary;
use Acme\Spellcheck\Filter\BaselineFilter;
use Acme\Spellcheck\Filter\DeduplicationFilter;
use Acme\Spellcheck\Filter\DictionaryFilter;
use Acme\Spellcheck\Filter\FilterChain;
use Acme\Spellcheck\Icu\IcuMessageParser;
use Acme\Spellcheck\Model\FragmentContext;
use Acme\Spellcheck\Model\Location;
use Acme\Spellcheck\Model\Misspelling;
use Acme\Spellcheck\Model\TextFragment;
use Acme\Spellcheck\Model\TokenizerMode;
use Acme\Spellcheck\Processor\HtmlProcessor;
use Acme\Spellcheck\Processor\IcuMessageProcessor;
use Acme\Spellcheck\Processor\LegacyPluralProcessor;
use Acme\Spellcheck\Processor\NormalizeApostropheProcessor;
use Acme\Spellcheck\Processor\PlaceholderProcessor;
use Acme\Spellcheck\Processor\ProcessorChain;
use Acme\Spellcheck\Processor\SprintfProcessor;
use Acme\Spellcheck\Processor\UrlProcessor;
use Acme\Spellcheck\Source\ArrayFragmentSource;
use Acme\Spellcheck\Speller\WordListSpeller;
use Acme\Spellcheck\Tokenizer\IdentifierSplitter;
use Acme\Spellcheck\Tokenizer\ProseTokenizer;
use Acme\Spellcheck\Tokenizer\TokenizerRegistry;
use PHPUnit\Framework\TestCase;

final class SpellcheckRunnerTest extends TestCase
{
    private const ITALIAN = [
        'ciao', 'hai', 'messaggi', 'inserisci', 'il', 'tuo', 'indirizzo', 'di',
        'spedizione', 'clicca', 'qui', 'una', 'mela', 'mele', 'nessuno', 'uno',
        'elementi', 'dell', 'utente', 'nome', 'scadenza', 'totale',
    ];

    private const ENGLISH = ['order', 'subscriber', 'occurred', 'the', 'event', 'when', 'error', 'listener'];

    private DiagnosticCollector $diagnostics;
    private RunStatisticsCollector $statistics;
    private BaselineFilter $baselineFilter;
    private SpellcheckRunner $runner;
    private RunConfiguration $config;

    protected function setUp(): void
    {
        $dictionary = new AggregateDictionary([
            new WordListDictionary(self::ITALIAN, 'it'),
            new WordListDictionary(self::ENGLISH, 'en'),
        ]);

        $this->diagnostics = new DiagnosticCollector();
        $this->statistics = new RunStatisticsCollector();
        $this->baselineFilter = new BaselineFilter($this->statistics);

        $this->runner = new SpellcheckRunner(
            new ProcessorChain([
                new NormalizeApostropheProcessor(),
                new LegacyPluralProcessor(),
                new IcuMessageProcessor(new IcuMessageParser(), IcuMessageProcessor::MODE_AUTO, $this->diagnostics),
                new PlaceholderProcessor(),
                new SprintfProcessor(),
                new HtmlProcessor(),
                new UrlProcessor(),
            ]),
            new TokenizerRegistry([new IdentifierSplitter(4), new ProseTokenizer(3)]),
            new WordListSpeller($dictionary, 3),
            new FilterChain([
                new DictionaryFilter($dictionary),
                new DeduplicationFilter(),
                $this->baselineFilter,
            ]),
            new MisspellingFactory(),
            $this->diagnostics,
            $this->statistics,
            $this->baselineFilter,
        );

        $this->config = new RunConfiguration(
            new LocaleDictionaryMap(['it' => 'it', 'en' => 'en']),
            diagnostics: $this->diagnostics,
        );
    }

    public function testPlaceholdersDoNotProduceFalsePositivesAndOffsetsSurvive(): void
    {
        $original = 'Ciao %name%, hai %count% messagi';

        $result = $this->run([$this->translation($original, 'inbox.count', 12)]);

        self::assertCount(1, $result->misspellings);
        self::assertSame('messagi', $result->misspellings[0]->word);
        self::assertSame(['messaggi'], $result->misspellings[0]->suggestions);

        // The column is the 1-based offset in the ORIGINAL message, not in the
        // placeholder-stripped one.
        self::assertSame(
            mb_strpos($original, 'messagi') + 1,
            $result->misspellings[0]->location?->column,
        );
        self::assertSame(12, $result->misspellings[0]->location?->line);
    }

    public function testHtmlMarkupIsIgnored(): void
    {
        $result = $this->run([$this->translation('<a href="/x" class="btn">Clicca qui</a>', 'cta')]);

        self::assertSame([], $this->words($result->misspellings));
    }

    public function testIcuBranchesAreCheckedAndKeywordsAreNot(): void
    {
        $result = $this->run([
            $this->translation('{n, plural, one {Hai una mela} other {Hai # mele}}', 'apples'),
        ]);

        self::assertSame([], $this->words($result->misspellings));
    }

    public function testLegacyPluralIntervalsAreStripped(): void
    {
        $result = $this->run([
            $this->translation('{0} Nessuno|]0,1] Uno|]1,Inf] %count% elementi', 'items'),
        ]);

        self::assertSame([], $this->words($result->misspellings));
    }

    public function testMalformedIcuProducesADiagnosticNotAnException(): void
    {
        $result = $this->run([$this->translation('{n, plural, one {Hai una mela', 'broken')]);

        self::assertSame([], $result->misspellings);
        self::assertCount(1, $result->diagnostics);
        self::assertSame('icu_syntax', $result->diagnostics[0]->code->value);
    }

    public function testIdentifierSuggestionsKeepTheParentShape(): void
    {
        $result = $this->run([
            new TextFragment(
                'OrderSuscriber',
                'en',
                FragmentContext::php('class_like', 'OrderSuscriber', 'src/OrderSuscriber.php'),
                Location::file('src/OrderSuscriber.php', 14),
                null,
                TokenizerMode::IDENTIFIER,
            ),
        ]);

        self::assertSame(['Suscriber'], $this->words($result->misspellings));
        self::assertSame(['OrderSubscriber'], $result->misspellings[0]->suggestions);
    }

    public function testResultsAreSortedByPathThenLine(): void
    {
        $result = $this->run([
            $this->translation('hai messagi', 'b', 30),
            $this->translation('Inserisci il tuo indirizio', 'a', 12),
            new TextFragment(
                'OrderSuscriber',
                'en',
                FragmentContext::php('class_like', 'OrderSuscriber', 'src/OrderSuscriber.php'),
                Location::file('src/OrderSuscriber.php', 14),
                null,
                TokenizerMode::IDENTIFIER,
            ),
        ]);

        self::assertSame(['Suscriber', 'indirizio', 'messagi'], $this->words($result->misspellings));
    }

    public function testUnknownLanguageIsSkippedWithADiagnostic(): void
    {
        $config = new RunConfiguration(
            new LocaleDictionaryMap([], ['it']),
            excludedLanguages: ['ja'],
            diagnostics: $this->diagnostics,
        );

        $result = $this->runner->run(
            [new ArrayFragmentSource([$this->translation('konnichiwa', 'greeting')->withLanguage('ja')])],
            $config,
        );

        self::assertSame([], $result->misspellings);
        self::assertSame('unsupported_language', $result->diagnostics[0]->code->value);
    }

    public function testBaselineSuppressesKnownIssuesAndCountsThem(): void
    {
        $fragments = [$this->translation('hai messagi', 'b'), $this->translation('Inserisci il tuo indirizio', 'a')];

        $first = $this->run($fragments);
        self::assertCount(2, $first->misspellings);

        $baseline = Baseline::fromMisspellings($first->misspellings, 'hash');
        $second = $this->runner->run([new ArrayFragmentSource($fragments)], $this->config, $baseline);

        self::assertSame([], $second->misspellings);
        self::assertSame(2, $second->stats->suppressedByBaseline);
        self::assertSame([], $second->outdatedBaselineEntries);
    }

    public function testBaselineEntriesNoLongerReproducedAreReportedAsOutdated(): void
    {
        $baseline = Baseline::fromMisspellings($this->run([$this->translation('hai messagi', 'b')])->misspellings);

        $result = $this->runner->run([new ArrayFragmentSource([])], $this->config, $baseline);

        self::assertCount(1, $result->outdatedBaselineEntries);
    }

    public function testTheSameWordAcrossManyFragmentsIsReportedOncePerContext(): void
    {
        $result = $this->run([
            $this->translation('hai messagi', 'a'),
            $this->translation('hai messagi', 'a'),
            $this->translation('hai messagi', 'b'),
        ]);

        self::assertCount(2, $result->misspellings, 'the duplicate context is collapsed, the distinct one is kept');
    }

    public function testStatisticsAreCollected(): void
    {
        $result = $this->run([$this->translation('Ciao %name%, hai %count% messagi', 'k')]);

        self::assertSame(1, $result->stats->fragments);
        self::assertGreaterThan(0, $result->stats->wordsChecked);
        self::assertGreaterThan(0.0, $result->stats->durationSeconds);
    }

    public function testExitCodes(): void
    {
        $withIssues = $this->run([$this->translation('hai messagi', 'k')]);
        self::assertSame(ExitCodeCalculator::ISSUES_FOUND, ExitCodeCalculator::calculate($withIssues, $this->config));

        $clean = $this->run([$this->translation('hai messaggi', 'k')]);
        self::assertSame(ExitCodeCalculator::SUCCESS, ExitCodeCalculator::calculate($clean, $this->config));

        $warned = $this->run([$this->translation('{n, plural, one {rotto', 'k')]);
        self::assertSame(ExitCodeCalculator::WARNINGS_ONLY, ExitCodeCalculator::calculate($warned, $this->config));
    }

    /**
     * @param list<TextFragment> $fragments
     */
    private function run(array $fragments): \Acme\Spellcheck\Checker\RunResult
    {
        return $this->runner->run([new ArrayFragmentSource($fragments)], $this->config);
    }

    private function translation(string $text, string $key, int $line = 1): TextFragment
    {
        return new TextFragment(
            $text,
            'it',
            FragmentContext::translation('it', 'messages', $key),
            Location::fileWithLogical('translations/messages.it.yaml', $line, 'it/messages/'.$key),
        );
    }

    /**
     * @param list<Misspelling> $misspellings
     *
     * @return list<string>
     */
    private function words(array $misspellings): array
    {
        return array_map(static fn (Misspelling $m): string => $m->word, $misspellings);
    }
}

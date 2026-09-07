<?php

declare(strict_types=1);

/**
 * Standalone smoke test: runs the parts of the engine that have no external
 * dependency, without composer. `php tests/smoke.php`
 *
 * The real test suite lives in tests/Unit and needs PHPUnit.
 */

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'PHPSpellcheck\\Core\\')) {
        return;
    }

    $relative = substr($class, \strlen('PHPSpellcheck\\Core\\'));
    $path = __DIR__.'/../src/'.str_replace('\\', '/', $relative).'.php';

    if (is_file($path)) {
        require $path;
    }
});

use PHPSpellcheck\Core\Baseline\Baseline;
use PHPSpellcheck\Core\Baseline\Fingerprint;
use PHPSpellcheck\Core\Checker\MisspellingFactory;
use PHPSpellcheck\Core\Checker\RunConfiguration;
use PHPSpellcheck\Core\Checker\RunStatisticsCollector;
use PHPSpellcheck\Core\Checker\SpellcheckRunner;
use PHPSpellcheck\Core\Diagnostics\DiagnosticCollector;
use PHPSpellcheck\Core\Dictionary\AggregateDictionary;
use PHPSpellcheck\Core\Dictionary\LocaleDictionaryMap;
use PHPSpellcheck\Core\Dictionary\WordListDictionary;
use PHPSpellcheck\Core\Filter\BaselineFilter;
use PHPSpellcheck\Core\Filter\DeduplicationFilter;
use PHPSpellcheck\Core\Filter\DictionaryFilter;
use PHPSpellcheck\Core\Filter\FilterChain;
use PHPSpellcheck\Core\Icu\IcuMessageParser;
use PHPSpellcheck\Core\Model\FragmentContext;
use PHPSpellcheck\Core\Model\Location;
use PHPSpellcheck\Core\Model\OffsetMap;
use PHPSpellcheck\Core\Model\TextFragment;
use PHPSpellcheck\Core\Model\TokenizerMode;
use PHPSpellcheck\Core\Processor\HtmlProcessor;
use PHPSpellcheck\Core\Processor\IcuMessageProcessor;
use PHPSpellcheck\Core\Processor\LegacyPluralProcessor;
use PHPSpellcheck\Core\Processor\NormalizeApostropheProcessor;
use PHPSpellcheck\Core\Processor\PlaceholderProcessor;
use PHPSpellcheck\Core\Processor\ProcessorChain;
use PHPSpellcheck\Core\Processor\SprintfProcessor;
use PHPSpellcheck\Core\Processor\UrlProcessor;
use PHPSpellcheck\Core\Report\BufferedWriter;
use PHPSpellcheck\Core\Report\GithubReporter;
use PHPSpellcheck\Core\Report\JsonReporter;
use PHPSpellcheck\Core\Report\TableReporter;
use PHPSpellcheck\Core\Source\ArrayFragmentSource;
use PHPSpellcheck\Core\Speller\WordListSpeller;
use PHPSpellcheck\Core\Tokenizer\IdentifierSplitter;
use PHPSpellcheck\Core\Tokenizer\ProseTokenizer;
use PHPSpellcheck\Core\Tokenizer\TokenizerRegistry;

$passed = 0;
$failed = 0;

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    global $passed, $failed;

    if ($expected === $actual) {
        ++$passed;

        return;
    }

    ++$failed;
    printf("FAIL  %s\n      expected: %s\n      actual:   %s\n", $message, json_encode($expected), json_encode($actual));
}

function assertTrue(bool $condition, string $message): void
{
    assertSame(true, $condition, $message);
}

// ---------------------------------------------------------------- OffsetMap

assertSame(7, OffsetMap::identity()->translate(7), 'identity map is transparent');

$map = new OffsetMap([[0, 0, 5], [5, 12, 7]]);
assertSame(3, $map->translate(3), 'offset inside the first segment');
assertSame(12, $map->translate(5), 'offset at the start of the second segment');
assertSame(18, $map->translate(11), 'offset inside the second segment');

$outer = new OffsetMap([[0, 0, 4], [4, 10, 6]]);
$inner = new OffsetMap([[0, 2, 6]]);
$composed = $outer->compose($inner);
assertSame(2, $composed->translate(0), 'compose: still inside the outer first segment');
assertSame(11, $composed->translate(3), 'compose: crosses into the outer second segment');

$a = new OffsetMap([[0, 1, 10]]);
$b = new OffsetMap([[0, 2, 6]]);
$c = new OffsetMap([[0, 1, 4]]);
for ($i = 0; $i < 4; ++$i) {
    assertSame(
        $a->compose($b)->compose($c)->translate($i),
        $a->compose($b->compose($c))->translate($i),
        'compose is associative at offset '.$i,
    );
}

// -------------------------------------------------------- IdentifierSplitter

$splitter = new IdentifierSplitter(1);
$cases = [
    'getUserById' => ['get', 'User', 'By', 'Id'],
    'HTTPResponseCode' => ['HTTP', 'Response', 'Code'],
    'first_name' => ['first', 'name'],
    'MAX_RETRY_COUNT' => ['MAX', 'RETRY', 'COUNT'],
    'utf8Encode' => ['utf8', 'Encode'],
    'DoctrineORMAdapter' => ['Doctrine', 'ORM', 'Adapter'],
    'IOException' => ['IO', 'Exception'],
    '__invoke' => ['invoke'],
    'città' => ['città'],
    'v2Migration' => ['v2', 'Migration'],
    'SomeClass_v1_2' => ['Some', 'Class', 'v1', '2'],
];

foreach ($cases as $identifier => $expected) {
    assertSame($expected, $splitter->split($identifier), 'split '.$identifier);
}

// ------------------------------------------------------- IcuMessageParser

$icu = new IcuMessageParser();

$texts = static fn (array $spans): array => array_map(static fn ($span): string => $span->text, $spans);

assertSame(['Ciao '], $texts($icu->parse('Ciao {name}')), 'ICU simple placeholder');
assertSame(
    ['Hai una mela', 'Hai ', ' mele'],
    $texts($icu->parse('{n, plural, one {Hai una mela} other {Hai # mele}}')),
    'ICU plural branches',
);
assertSame(
    ['Benvenuta', 'Benvenuto'],
    $texts($icu->parse('{g, select, female {Benvenuta} other {Benvenuto}}')),
    'ICU select branches',
);
assertSame(["l'utente"], $texts($icu->parse("l''utente")), 'ICU doubled apostrophe');
// A quoted section is contiguous with the surrounding text, so it belongs to
// the same span rather than starting a new one.
assertSame(['usa {placeholder}'], $texts($icu->parse("usa '{'placeholder'}'")), 'ICU brace quoting');
assertSame(["it's fine"], $texts($icu->parse("it's fine")), 'ICU plain apostrophe');
assertSame([' totale'], $texts($icu->parse('{v, number, ::currency/EUR} totale')), 'ICU number style skipped');
assertSame(['uno', 'altri'], $texts($icu->parse('{n, plural, offset:1 one {uno} other {altri}}')), 'ICU offset');
assertSame(
    ['rosso', 'blu', 'molti'],
    $texts($icu->parse('{a, plural, one {{b, select, x {rosso} other {blu}}} other {molti}}')),
    'ICU nested arguments',
);

$thrown = false;
try {
    $icu->parse('{n, plural, one {x');
} catch (\PHPSpellcheck\Core\Exception\IcuSyntaxException) {
    $thrown = true;
}
assertTrue($thrown, 'malformed ICU throws IcuSyntaxException');

$spans = $icu->parse('{n, plural, one {Hai una mela} other {altro}}');
assertSame(17, $spans[0]->offset, 'ICU span offset points at the original message');

// ------------------------------------------------------------ full pipeline

$dictionary = new AggregateDictionary([
    new WordListDictionary([
        'ciao', 'hai', 'messaggi', 'inserisci', 'il', 'tuo', 'indirizzo', 'di',
        'spedizione', 'clicca', 'qui', 'una', 'mela', 'mele', 'nessuno', 'uno',
        'elementi', 'dell', 'utente', 'nome',
    ], 'it'),
    new WordListDictionary(['order', 'subscriber', 'occurred', 'the', 'event', 'when', 'error'], 'en'),
]);

$diagnostics = new DiagnosticCollector();
$statistics = new RunStatisticsCollector();
$baselineFilter = new BaselineFilter($statistics);

$processors = new ProcessorChain([
    new NormalizeApostropheProcessor(),
    new LegacyPluralProcessor(),
    new IcuMessageProcessor(new IcuMessageParser(), IcuMessageProcessor::MODE_AUTO, $diagnostics),
    new PlaceholderProcessor(),
    new SprintfProcessor(),
    new HtmlProcessor(),
    new UrlProcessor(),
]);

$runner = new SpellcheckRunner(
    $processors,
    new TokenizerRegistry([new IdentifierSplitter(4), new ProseTokenizer(3)]),
    new WordListSpeller($dictionary, 3),
    new FilterChain([new DictionaryFilter($dictionary), new DeduplicationFilter(), $baselineFilter]),
    new MisspellingFactory(),
    $diagnostics,
    $statistics,
    $baselineFilter,
);

$config = new RunConfiguration(
    new LocaleDictionaryMap(['it' => 'it', 'en' => 'en']),
    true,
    3,
    true,
    true,
    false,
    false,
    false,
    [],
    $diagnostics,
);

$fragments = [
    new TextFragment(
        'Ciao %name%, hai %count% messagi',
        'it',
        FragmentContext::translation('it', 'messages', 'inbox.count'),
        Location::fileWithLogical('translations/messages.it.yaml', 12, 'it/messages/inbox.count'),
    ),
    new TextFragment(
        '<a href="/x">Clicca qui</a>',
        'it',
        FragmentContext::translation('it', 'messages', 'cta'),
        Location::fileWithLogical('translations/messages.it.yaml', 20, 'it/messages/cta'),
    ),
    new TextFragment(
        'Inserisci il tuo indirizio di spedizione',
        'it',
        FragmentContext::translation('it', 'messages', 'checkout.shipping.address'),
        Location::fileWithLogical('translations/messages.it.yaml', 30, 'it/messages/checkout.shipping.address'),
    ),
    new TextFragment(
        '{0} Nessuno|]0,1] Uno|]1,Inf] %count% elementi',
        'it',
        FragmentContext::translation('it', 'messages', 'items'),
        Location::fileWithLogical('translations/messages.it.yaml', 40, 'it/messages/items'),
    ),
    new TextFragment(
        'OrderSuscriber',
        'en',
        FragmentContext::php('class_like', 'OrderSuscriber', 'src/EventListener/OrderSuscriber.php'),
        Location::file('src/EventListener/OrderSuscriber.php', 14),
        null,
        TokenizerMode::IDENTIFIER,
    ),
];

$result = $runner->run([new ArrayFragmentSource($fragments)], $config);

$words = array_map(static fn ($m): string => $m->word, $result->misspellings);
// Ordering is path, then line: src/ before translations/, then line 12 before 30.
assertSame(['Suscriber', 'messagi', 'indirizio'], $words, 'pipeline reports exactly the expected words');

$byWord = [];
foreach ($result->misspellings as $misspelling) {
    $byWord[$misspelling->word] = $misspelling;
}

// FR-503: the column is the offset in the ORIGINAL message, not in the
// placeholder-stripped one.
assertSame(26, $byWord['messagi']->location->column, 'offset survives placeholder removal');
assertSame(12, $byWord['messagi']->location->line, 'line comes from the catalogue file');
assertSame(['messaggi'], $byWord['messagi']->suggestions, 'suggestion for messagi');
assertSame(['indirizzo'], $byWord['indirizio']->suggestions, 'suggestion for indirizio');

// FR-304.2: the suggestion is reshaped in the identifier convention.
assertSame(['OrderSubscriber'], $byWord['Suscriber']->suggestions, 'identifier suggestions keep the parent shape');

assertSame(0, \count($result->diagnostics), 'no diagnostics on a clean run');
assertTrue($result->stats->wordsChecked > 10, 'words were actually checked');

// --------------------------------------------------------------- baseline

$baseline = Baseline::fromMisspellings($result->misspellings, 'testhash');
assertSame(3, $baseline->count(), 'baseline records every issue');

$second = $runner->run([new ArrayFragmentSource($fragments)], $config, $baseline);
assertSame(0, \count($second->misspellings), 'baseline suppresses known issues');
assertSame(3, $second->stats->suppressedByBaseline, 'suppressed issues are counted');
assertSame([], $second->outdatedBaselineEntries, 'nothing is outdated on an identical run');

// FR-402.1: moving a line does not change the fingerprint.
$moved = new TextFragment(
    'Inserisci il tuo indirizio di spedizione',
    'it',
    FragmentContext::translation('it', 'messages', 'checkout.shipping.address'),
    Location::fileWithLogical('translations/messages.it.yaml', 999, 'it/messages/checkout.shipping.address'),
);
$movedResult = $runner->run([new ArrayFragmentSource([$moved])], $config);
assertSame(
    $byWord['indirizio']->fingerprint(),
    $movedResult->misspellings[0]->fingerprint(),
    'fingerprint is stable across line shifts',
);
assertSame(
    Fingerprint::compute('translation|it|messages|checkout.shipping.address', 'indirizio', 'spelling'),
    $byWord['indirizio']->fingerprint(),
    'fingerprint is computed from the documented seed',
);

// --------------------------------------------------------------- reporters

$writer = new BufferedWriter(false);
(new GithubReporter())->report($result, $writer);
$github = $writer->getBuffer();
assertTrue(
    str_contains($github, '::error file=translations/messages.it.yaml,line=12,col=26,title=Spelling::'),
    'github annotation carries file, line and column',
);
assertTrue(str_contains($github, 'Unknown word "messagi"'), 'github annotation names the word');
assertSame('a %25 b', GithubReporter::escape('a % b'), 'github escaping of percent signs');

$writer->reset();
(new JsonReporter())->report($result, $writer);
$json = json_decode($writer->getBuffer(), true);
assertSame(3, $json['summary']['new'], 'json summary counts the issues');
assertSame('indirizio', $json['issues'][2]['word'], 'json issues are ordered deterministically');

$writer->reset();
(new TableReporter())->report($result, $writer);
assertTrue(str_contains($writer->getBuffer(), '3 new issues.'), 'table summary');
assertTrue(str_contains($writer->getBuffer(), 'Translations'), 'table groups by source');

// ------------------------------------------------------------------ report

printf("\n%d passed, %d failed\n", $passed, $failed);

exit($failed > 0 ? 1 : 0);

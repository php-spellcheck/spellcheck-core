<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Tests\Unit\Report;

use PHPSpellcheck\Core\Checker\RunResult;
use PHPSpellcheck\Core\Checker\RunStatistics;
use PHPSpellcheck\Core\Model\Diagnostic;
use PHPSpellcheck\Core\Model\DiagnosticCode;
use PHPSpellcheck\Core\Model\FragmentContext;
use PHPSpellcheck\Core\Model\Location;
use PHPSpellcheck\Core\Model\Misspelling;
use PHPSpellcheck\Core\Model\MisspellingType;
use PHPSpellcheck\Core\Report\BufferedWriter;
use PHPSpellcheck\Core\Report\GithubReporter;
use PHPUnit\Framework\TestCase;

final class GithubReporterTest extends TestCase
{
    public function testAnnotationCarriesFileLineAndColumn(): void
    {
        $output = $this->report([$this->translationMisspelling()]);

        self::assertStringContainsString(
            '::error file=translations/messages.it.yaml,line=12,col=26,title=Spelling::',
            $output,
        );
        self::assertStringContainsString('Unknown word "messagi"', $output);
        self::assertStringContainsString('did you mean messaggi?', $output);
        self::assertStringContainsString('[it/messages]', $output);
    }

    public function testAnnotationWithoutLocation(): void
    {
        $misspelling = new Misspelling(
            'messagi',
            MisspellingType::SPELLING,
            [],
            'it_IT',
            FragmentContext::translation('it', 'messages', 'k'),
        );

        self::assertStringContainsString('::error title=Spelling::', $this->report([$misspelling]));
    }

    public function testMessageIsEscaped(): void
    {
        self::assertSame('a %25 b', GithubReporter::escape('a % b'));
        self::assertSame('a%0Ab', GithubReporter::escape("a\nb"));
        self::assertSame('a%0Db', GithubReporter::escape("a\rb"));
    }

    public function testDiagnosticsBecomeWarnings(): void
    {
        $result = new RunResult(
            [],
            [new Diagnostic(DiagnosticCode::MISSING_DICTIONARY, 'No dictionary for "de".')],
            new RunStatistics(),
        );

        $writer = new BufferedWriter();
        (new GithubReporter())->report($result, $writer);

        self::assertStringContainsString('::warning title=Spellcheck::No dictionary for "de".', $writer->getBuffer());
    }

    /**
     * @param list<Misspelling> $misspellings
     */
    private function report(array $misspellings): string
    {
        $writer = new BufferedWriter();
        (new GithubReporter())->report(new RunResult($misspellings, [], new RunStatistics()), $writer);

        return $writer->getBuffer();
    }

    private function translationMisspelling(): Misspelling
    {
        return new Misspelling(
            'messagi',
            MisspellingType::SPELLING,
            ['messaggi'],
            'it_IT',
            FragmentContext::translation('it', 'messages', 'inbox.count'),
            Location::fileWithLogical('translations/messages.it.yaml', 12, 'it/messages/inbox.count')->withColumn(26),
            'Ciao, hai messagi',
        );
    }
}

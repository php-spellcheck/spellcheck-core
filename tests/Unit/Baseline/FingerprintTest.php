<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Tests\Unit\Baseline;

use PHPSpellcheck\Core\Baseline\Fingerprint;
use PHPSpellcheck\Core\Model\FragmentContext;
use PHPSpellcheck\Core\Model\Location;
use PHPSpellcheck\Core\Model\Misspelling;
use PHPSpellcheck\Core\Model\MisspellingType;
use PHPUnit\Framework\TestCase;

final class FingerprintTest extends TestCase
{
    public function testIsUnaffectedByLineShift(): void
    {
        $first = $this->misspelling(Location::file('src/Foo.php', 10));
        $second = $this->misspelling(Location::file('src/Foo.php', 400));

        self::assertSame($first->fingerprint(), $second->fingerprint());
    }

    public function testIsUnaffectedByExcerptAndSuggestions(): void
    {
        $first = new Misspelling('Suscriber', MisspellingType::SPELLING, ['Subscriber'], 'en_US', $this->context(), null, 'a');
        $second = new Misspelling('Suscriber', MisspellingType::SPELLING, [], 'en_US', $this->context(), null, 'b');

        self::assertSame($first->fingerprint(), $second->fingerprint());
    }

    public function testChangesWhenTheFileIsRenamed(): void
    {
        $first = $this->misspelling(null, 'src/Foo.php');
        $second = $this->misspelling(null, 'src/Bar.php');

        self::assertNotSame($first->fingerprint(), $second->fingerprint());
    }

    public function testIsCaseInsensitiveOnTheWord(): void
    {
        $lower = new Misspelling('suscriber', MisspellingType::SPELLING, [], 'en_US', $this->context());
        $upper = new Misspelling('SUSCRIBER', MisspellingType::SPELLING, [], 'en_US', $this->context());

        self::assertSame($lower->fingerprint(), $upper->fingerprint());
    }

    public function testMatchesTheDocumentedSeed(): void
    {
        $misspelling = new Misspelling(
            'indirizio',
            MisspellingType::SPELLING,
            [],
            'it_IT',
            FragmentContext::translation('it', 'messages', 'checkout.shipping.address'),
        );

        self::assertSame(
            Fingerprint::compute('translation|it|messages|checkout.shipping.address', 'indirizio', 'spelling'),
            $misspelling->fingerprint(),
        );
    }

    public function testIsSixteenHexCharacters(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $this->misspelling(null)->fingerprint());
    }

    private function context(string $path = 'src/Foo.php'): FragmentContext
    {
        return FragmentContext::php('class_like', 'OrderSuscriber', $path);
    }

    private function misspelling(?Location $location, string $path = 'src/Foo.php'): Misspelling
    {
        return new Misspelling(
            'Suscriber',
            MisspellingType::SPELLING,
            ['Subscriber'],
            'en_US',
            $this->context($path),
            $location,
        );
    }
}

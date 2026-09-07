<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Tests\Unit\Baseline;

use PHPSpellcheck\Core\Baseline\Baseline;
use PHPSpellcheck\Core\Baseline\BaselineStorage;
use PHPSpellcheck\Core\Exception\BaselineSchemaException;
use PHPSpellcheck\Core\Model\FragmentContext;
use PHPSpellcheck\Core\Model\Location;
use PHPSpellcheck\Core\Model\Misspelling;
use PHPSpellcheck\Core\Model\MisspellingType;
use PHPUnit\Framework\TestCase;

final class BaselineStorageTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/spellcheck-baseline-'.bin2hex(random_bytes(6)).'/baseline.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
            @rmdir(\dirname($this->path));
        }
    }

    public function testRoundTrip(): void
    {
        $storage = new BaselineStorage();
        $baseline = Baseline::fromMisspellings([$this->misspelling('indirizio'), $this->misspelling('messagi')], 'confighash');

        $storage->save($this->path, $baseline);
        $loaded = $storage->load($this->path);

        self::assertSame(2, $loaded->count());
        self::assertSame('confighash', $loaded->configHash());
        self::assertTrue($loaded->contains($this->misspelling('indirizio')->fingerprint()));
    }

    public function testSaveIsDeterministic(): void
    {
        $storage = new BaselineStorage();
        $baseline = Baseline::fromMisspellings([$this->misspelling('b'), $this->misspelling('a')]);

        $storage->save($this->path, $baseline);
        $first = (string) file_get_contents($this->path);

        usleep(1100000);

        $storage->save($this->path, Baseline::fromMisspellings([$this->misspelling('a'), $this->misspelling('b')]));
        $second = (string) file_get_contents($this->path);

        self::assertSame($first, $second, 'an unchanged baseline must be byte identical, timestamp included');
    }

    public function testMissingFileYieldsAnEmptyBaseline(): void
    {
        self::assertTrue((new BaselineStorage())->load($this->path)->isEmpty());
    }

    public function testInvalidJsonIsRejected(): void
    {
        $this->write('{not json');

        $this->expectException(BaselineSchemaException::class);
        $this->expectExceptionMessageMatches('/not valid JSON/');

        (new BaselineStorage())->load($this->path);
    }

    public function testWrongSchemaIsRejectedWithAnActionableMessage(): void
    {
        $this->write('{"schema": 99, "entries": {}}');

        $this->expectException(BaselineSchemaException::class);
        $this->expectExceptionMessageMatches('/spellcheck:baseline/');

        (new BaselineStorage())->load($this->path);
    }

    public function testOutdatedEntriesAreDetectedAndPruned(): void
    {
        $baseline = Baseline::fromMisspellings([$this->misspelling('a'), $this->misspelling('b')]);

        $baseline->contains($this->misspelling('a')->fingerprint());

        self::assertSame([$this->misspelling('b')->fingerprint()], $baseline->outdated());
        self::assertSame(1, $baseline->withoutOutdated()->count());
    }

    private function write(string $contents): void
    {
        $directory = \dirname($this->path);

        if (!is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }

        file_put_contents($this->path, $contents);
    }

    private function misspelling(string $word): Misspelling
    {
        return new Misspelling(
            $word,
            MisspellingType::SPELLING,
            [],
            'it_IT',
            FragmentContext::translation('it', 'messages', 'key.'.$word),
            Location::file('translations/messages.it.yaml', 3),
        );
    }
}

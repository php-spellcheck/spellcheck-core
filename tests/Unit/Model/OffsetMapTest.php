<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Tests\Unit\Model;

use Acme\Spellcheck\Model\OffsetMap;
use Acme\Spellcheck\Model\OffsetMapBuilder;
use PHPUnit\Framework\TestCase;

final class OffsetMapTest extends TestCase
{
    public function testIdentityIsTransparent(): void
    {
        $map = OffsetMap::identity();

        self::assertTrue($map->isIdentity());
        self::assertSame(0, $map->translate(0));
        self::assertSame(42, $map->translate(42));
    }

    public function testTranslateInsideSegments(): void
    {
        $map = new OffsetMap([[0, 0, 5], [5, 12, 7]]);

        self::assertSame(0, $map->translate(0));
        self::assertSame(3, $map->translate(3));
        self::assertSame(12, $map->translate(5));
        self::assertSame(18, $map->translate(11));
    }

    public function testTranslateOutsideSegmentsClampsToPreviousEnd(): void
    {
        $map = new OffsetMap([[0, 0, 3]]);

        self::assertSame(3, $map->translate(10));
    }

    public function testTranslateBeforeFirstSegmentUsesItsOrigin(): void
    {
        $map = new OffsetMap([[5, 20, 3]]);

        self::assertSame(20, $map->translate(0));
    }

    public function testComposeWithIdentityIsNeutral(): void
    {
        $map = new OffsetMap([[0, 4, 6]]);

        self::assertSame($map, $map->compose(OffsetMap::identity()));
        self::assertSame($map, OffsetMap::identity()->compose($map));
    }

    public function testComposeSplitsAcrossOuterSegments(): void
    {
        $outer = new OffsetMap([[0, 0, 4], [4, 10, 6]]);
        $inner = new OffsetMap([[0, 2, 6]]);

        $composed = $outer->compose($inner);

        self::assertSame(2, $composed->translate(0));
        self::assertSame(3, $composed->translate(1));
        self::assertSame(10, $composed->translate(2));
        self::assertSame(11, $composed->translate(3));
    }

    /**
     * @dataProvider associativityProvider
     */
    public function testComposeIsAssociative(OffsetMap $a, OffsetMap $b, OffsetMap $c, int $length): void
    {
        $left = $a->compose($b)->compose($c);
        $right = $a->compose($b->compose($c));

        for ($offset = 0; $offset < $length; ++$offset) {
            self::assertSame(
                $left->translate($offset),
                $right->translate($offset),
                sprintf('composition differs at offset %d', $offset),
            );
        }
    }

    /**
     * @return iterable<string, array{OffsetMap, OffsetMap, OffsetMap, int}>
     */
    public static function associativityProvider(): iterable
    {
        yield 'single segments' => [
            new OffsetMap([[0, 1, 10]]),
            new OffsetMap([[0, 2, 6]]),
            new OffsetMap([[0, 1, 4]]),
            4,
        ];

        yield 'multiple segments' => [
            new OffsetMap([[0, 0, 3], [3, 8, 5], [8, 20, 4]]),
            new OffsetMap([[0, 1, 4], [4, 7, 5]]),
            new OffsetMap([[0, 0, 3], [3, 5, 3]]),
            6,
        ];
    }

    public function testNormalizeMergesContiguousSegments(): void
    {
        $map = new OffsetMap([[0, 0, 2], [2, 2, 3]]);
        $composed = $map->compose(new OffsetMap([[0, 0, 5]]));

        self::assertCount(1, $composed->getSegments());
        self::assertSame([0, 0, 5], $composed->getSegments()[0]);
    }

    public function testShiftMovesOriginalOffsets(): void
    {
        $map = (new OffsetMap([[0, 2, 4]]))->shift(10);

        self::assertSame(12, $map->translate(0));
    }

    public function testBuilderProducesConsistentTextAndMap(): void
    {
        $original = 'Ciao %name%, hai %count% messagi';

        $builder = new OffsetMapBuilder();
        $builder->keep('Ciao ', 0);
        $builder->emit(' ');
        $builder->keep(', hai ', 11);
        $builder->emit(' ');
        $builder->keep(' messagi', 24);

        self::assertSame('Ciao  , hai   messagi', $builder->getText());

        $map = $builder->getMap();
        $wordOffsetInTransformed = mb_strpos($builder->getText(), 'messagi');

        self::assertNotFalse($wordOffsetInTransformed);
        self::assertSame(
            mb_strpos($original, 'messagi'),
            $map->translate($wordOffsetInTransformed),
        );
    }
}

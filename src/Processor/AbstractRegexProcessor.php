<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Processor;

use PHPSpellcheck\Core\Model\OffsetMapBuilder;
use PHPSpellcheck\Core\Model\TextFragment;
use PHPSpellcheck\Core\Support\Utf8;

/**
 * Base class for processors that simply blank out everything matching a
 * pattern, keeping the offsets of what remains.
 */
abstract class AbstractRegexProcessor implements TextProcessorInterface
{
    abstract protected function getPattern(): string;

    public function supports(TextFragment $fragment): bool
    {
        return !$fragment->isBlank();
    }

    public function process(TextFragment $fragment): TextFragment|array
    {
        $subject = $fragment->text;

        preg_match_all($this->getPattern(), $subject, $matches, \PREG_OFFSET_CAPTURE);

        if ([] === ($matches[0] ?? [])) {
            return $fragment;
        }

        $ascii = Utf8::isAscii($subject);
        $builder = new OffsetMapBuilder();
        $cursor = 0;

        /** @var array{0: string, 1: int} $match */
        foreach ($matches[0] as $match) {
            [$text, $byteOffset] = $match;

            if (-1 === $byteOffset) {
                continue;
            }

            $charOffset = Utf8::byteToCharOffset($subject, $byteOffset, $ascii);

            if ($charOffset < $cursor) {
                continue;
            }

            $builder->keep(mb_substr($subject, $cursor, $charOffset - $cursor), $cursor);
            $builder->emit($this->getReplacement($text));

            $cursor = $charOffset + mb_strlen($text);
        }

        $builder->keep(mb_substr($subject, $cursor), $cursor);

        return $fragment->withText($builder->getText(), $builder->getMap());
    }

    protected function getReplacement(string $match): string
    {
        return ' ';
    }
}

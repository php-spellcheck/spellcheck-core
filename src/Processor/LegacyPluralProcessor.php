<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Processor;

use Acme\Spellcheck\Model\FragmentContext;
use Acme\Spellcheck\Model\TextFragment;

/**
 * Splits the legacy pipe based pluralisation into independent fragments and
 * strips explicit rules and intervals.
 *
 *   "{0} Nessuno|]0,1] Uno|]1,Inf] %count% elementi"
 *      -> "Nessuno", "Uno", "%count% elementi"
 *
 * A doubled pipe ("||") is an escaped pipe and does not split.
 */
final class LegacyPluralProcessor implements TextProcessorInterface
{
    private const RULE_PREFIX = '/^\s*(?:\{\s*[\d\s,]+\s*\}|[\[\]]\s*-?(?:Inf|\d+(?:\.\d+)?)\s*,\s*-?(?:Inf|\d+(?:\.\d+)?)\s*[\[\]])\s*/u';

    public static function getDefaultPriority(): int
    {
        return 900;
    }

    public function supports(TextFragment $fragment): bool
    {
        return FragmentContext::SOURCE_TRANSLATION === $fragment->context->sourceType
            && str_contains($fragment->text, '|')
            && !$fragment->isBlank();
    }

    public function process(TextFragment $fragment): TextFragment|array
    {
        $parts = $this->split($fragment->text);

        if (\count($parts) < 2) {
            return $fragment;
        }

        $offsets = $fragment->getOffsets();
        $fragments = [];

        foreach ($parts as [$text, $offset]) {
            $trimmedOffset = $offset;
            $stripped = $text;

            if (1 === preg_match(self::RULE_PREFIX, $text, $m)) {
                $stripped = mb_substr($text, mb_strlen($m[0]));
                $trimmedOffset += mb_strlen($m[0]);
            }

            $leading = mb_strlen($stripped) - mb_strlen(ltrim($stripped));
            $stripped = trim($stripped);
            $trimmedOffset += $leading;

            if ('' === $stripped) {
                continue;
            }

            $fragments[] = $fragment->derive($stripped, $offsets->translate($trimmedOffset));
        }

        return $fragments;
    }

    /**
     * @return list<array{0: string, 1: int}> text and character offset
     */
    private function split(string $message): array
    {
        $chars = mb_str_split($message);
        $count = \count($chars);

        $parts = [];
        $buffer = '';
        $start = 0;

        for ($i = 0; $i < $count; ++$i) {
            if ('|' === $chars[$i]) {
                if ($i + 1 < $count && '|' === $chars[$i + 1]) {
                    $buffer .= '|';
                    ++$i;

                    continue;
                }

                $parts[] = [$buffer, $start];
                $buffer = '';
                $start = $i + 1;

                continue;
            }

            $buffer .= $chars[$i];
        }

        $parts[] = [$buffer, $start];

        return $parts;
    }
}

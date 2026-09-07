<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Processor;

use PHPSpellcheck\Core\Model\OffsetMap;
use PHPSpellcheck\Core\Model\TextFragment;

/**
 * Normalises typographic apostrophes and non breaking spaces. The replacement
 * is length preserving, so the offset map stays the identity.
 */
final class NormalizeApostropheProcessor implements TextProcessorInterface
{
    private const REPLACEMENTS = [
        "\u{2019}" => "'",
        "\u{2018}" => "'",
        "\u{02BC}" => "'",
        "\u{00A0}" => ' ',
        "\u{202F}" => ' ',
    ];

    public static function getDefaultPriority(): int
    {
        return 1000;
    }

    public function supports(TextFragment $fragment): bool
    {
        return !$fragment->isBlank();
    }

    public function process(TextFragment $fragment): TextFragment
    {
        $text = strtr($fragment->text, self::REPLACEMENTS);

        if ($text === $fragment->text) {
            return $fragment;
        }

        // Same character count: identity map.
        return $fragment->withText($text, OffsetMap::identity());
    }
}

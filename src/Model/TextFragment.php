<?php

declare(strict_types=1);

namespace Acme\Spellcheck\Model;

/**
 * The unit that travels through the pipeline.
 *
 * @psalm-immutable
 */
final class TextFragment
{
    private readonly OffsetMap $offsetMap;

    public function __construct(
        public readonly string $text,
        public readonly string $language,
        public readonly FragmentContext $context,
        public readonly ?Location $location = null,
        ?OffsetMap $offsets = null,
        public readonly TokenizerMode $tokenizer = TokenizerMode::PROSE,
        public readonly string $originalText = '',
    ) {
        $this->offsetMap = $offsets ?? OffsetMap::identity();
    }

    public function getOffsets(): OffsetMap
    {
        return $this->offsetMap;
    }

    public function getOriginalText(): string
    {
        return '' !== $this->originalText ? $this->originalText : $this->text;
    }

    /**
     * Replaces the text, composing the new map with the existing one so that
     * offsets keep pointing at the original text through any number of
     * processors.
     */
    public function withText(string $text, OffsetMap $map): self
    {
        return new self(
            $text,
            $this->language,
            $this->context,
            $this->location,
            $this->offsetMap->compose($map),
            $this->tokenizer,
            $this->getOriginalText(),
        );
    }

    /**
     * Extracts a sub-fragment, keeping offsets relative to the original text.
     * $start and $length are expressed in characters of the current text.
     */
    public function slice(int $start, ?int $length = null): self
    {
        $text = mb_substr($this->text, $start, $length);
        $map = new OffsetMap([[0, $this->offsetMap->translate($start), mb_strlen($text)]]);

        return new self(
            $text,
            $this->language,
            $this->context,
            $this->location,
            $map,
            $this->tokenizer,
            $this->getOriginalText(),
        );
    }

    /**
     * Builds a sibling fragment from an explicit text and an offset in the
     * original text. Used by the ICU and legacy-plural processors.
     */
    public function derive(string $text, int $originalOffset): self
    {
        return new self(
            $text,
            $this->language,
            $this->context,
            $this->location,
            new OffsetMap([[0, $originalOffset, mb_strlen($text)]]),
            $this->tokenizer,
            $this->getOriginalText(),
        );
    }

    public function withLanguage(string $language): self
    {
        return new self(
            $this->text,
            $language,
            $this->context,
            $this->location,
            $this->offsetMap,
            $this->tokenizer,
            $this->getOriginalText(),
        );
    }

    public function withTokenizer(TokenizerMode $mode): self
    {
        return new self(
            $this->text,
            $this->language,
            $this->context,
            $this->location,
            $this->offsetMap,
            $mode,
            $this->getOriginalText(),
        );
    }

    public function isBlank(): bool
    {
        return '' === trim($this->text);
    }
}

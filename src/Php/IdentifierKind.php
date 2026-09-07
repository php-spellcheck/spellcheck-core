<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Php;

use PHPSpellcheck\Core\Model\TokenizerMode;

enum IdentifierKind: string
{
    case CLASS_LIKE = 'class_like';
    case METHOD = 'method';
    case FUNCTION = 'function';
    case PROPERTY = 'property';
    case PARAMETER = 'parameter';
    case VARIABLE = 'variable';
    case CONSTANT = 'constant';
    case NAMESPACE = 'namespace';
    case DOCBLOCK = 'docblock';
    case COMMENT = 'comment';
    case STRING_LITERAL = 'string_literal';

    /**
     * Categories enabled by default: variables and string literals are too
     * noisy to be worth it out of the box.
     */
    public const DEFAULTS = [
        self::CLASS_LIKE,
        self::METHOD,
        self::FUNCTION,
        self::PROPERTY,
        self::PARAMETER,
        self::CONSTANT,
        self::DOCBLOCK,
        self::COMMENT,
    ];

    public function tokenizerMode(): TokenizerMode
    {
        return match ($this) {
            self::DOCBLOCK, self::COMMENT => TokenizerMode::DOCBLOCK,
            self::STRING_LITERAL => TokenizerMode::PROSE,
            default => TokenizerMode::IDENTIFIER,
        };
    }

    public function isComment(): bool
    {
        return self::DOCBLOCK === $this || self::COMMENT === $this;
    }

    /**
     * @param list<string> $names
     *
     * @return list<self>
     */
    public static function fromNames(array $names): array
    {
        $kinds = [];

        foreach ($names as $name) {
            $kind = self::tryFrom($name);

            if (null !== $kind) {
                $kinds[] = $kind;
            }
        }

        return $kinds;
    }
}

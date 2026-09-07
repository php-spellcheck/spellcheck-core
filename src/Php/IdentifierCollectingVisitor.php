<?php

declare(strict_types=1);

namespace PHPSpellcheck\Core\Php;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Collects the identifiers and comments of a file.
 *
 * enterNode() deliberately declares no return type: php-parser 4 and 5 differ
 * on the allowed return values.
 */
final class IdentifierCollectingVisitor extends NodeVisitorAbstract
{
    /** @var list<CollectedIdentifier> */
    private array $collected = [];

    /** @var array<string, true> */
    private array $enabled = [];

    /** @var class-string */
    private readonly string $propertyItemClass;

    /**
     * @param list<IdentifierKind> $kinds
     */
    public function __construct(array $kinds)
    {
        foreach ($kinds as $kind) {
            $this->enabled[$kind->value] = true;
        }

        $this->propertyItemClass = NodeClasses::propertyItem();
    }

    /**
     * @return list<CollectedIdentifier>
     */
    public function getCollected(): array
    {
        return $this->collected;
    }

    public function beforeTraverse(array $nodes)
    {
        $this->collected = [];

        return null;
    }

    public function enterNode(Node $node)
    {
        $this->collectComments($node);

        if ($node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Enum_
        ) {
            if (null !== $node->name) {
                $this->add(IdentifierKind::CLASS_LIKE, (string) $node->name, $node->name->getStartLine());
            }

            return null;
        }

        if ($node instanceof Node\Stmt\Namespace_) {
            if (null !== $node->name) {
                // getParts() is not available in every php-parser 4 release.
                foreach (explode('\\', $node->name->toString()) as $part) {
                    $this->add(IdentifierKind::NAMESPACE, $part, $node->getStartLine());
                }
            }

            return null;
        }

        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->add(IdentifierKind::METHOD, (string) $node->name, $node->name->getStartLine());

            return null;
        }

        if ($node instanceof Node\Stmt\Function_) {
            $this->add(IdentifierKind::FUNCTION, (string) $node->name, $node->name->getStartLine());

            return null;
        }

        if ($node instanceof Node\Param) {
            $this->collectParam($node);

            return null;
        }

        if ($node instanceof Node\Const_) {
            $this->add(IdentifierKind::CONSTANT, (string) $node->name, $node->getStartLine());

            return null;
        }

        if ($node instanceof Node\Stmt\EnumCase) {
            $this->add(IdentifierKind::CONSTANT, (string) $node->name, $node->getStartLine());

            return null;
        }

        if ($node instanceof Node\Expr\Variable && \is_string($node->name)) {
            $this->add(IdentifierKind::VARIABLE, $node->name, $node->getStartLine());

            return null;
        }

        if ($node instanceof Node\Scalar\String_) {
            $this->add(IdentifierKind::STRING_LITERAL, $node->value, $node->getStartLine());

            return null;
        }

        if ($node instanceof $this->propertyItemClass) {
            /** @phpstan-ignore-next-line property name differs between php-parser 4 and 5 node classes */
            $this->add(IdentifierKind::PROPERTY, (string) $node->name, $node->getStartLine());
        }

        return null;
    }

    /**
     * A promoted constructor property is a property, not a parameter: it must
     * not be reported twice.
     */
    private function collectParam(Node\Param $param): void
    {
        if (!$param->var instanceof Node\Expr\Variable || !\is_string($param->var->name)) {
            return;
        }

        $promoted = 0 !== $param->flags;

        $this->add(
            $promoted ? IdentifierKind::PROPERTY : IdentifierKind::PARAMETER,
            $param->var->name,
            $param->getStartLine(),
        );
    }

    private function collectComments(Node $node): void
    {
        foreach ($node->getComments() as $comment) {
            $kind = $comment instanceof Comment\Doc
                ? IdentifierKind::DOCBLOCK
                : IdentifierKind::COMMENT;

            $this->add($kind, $comment->getText(), $comment->getStartLine());
        }
    }

    private function add(IdentifierKind $kind, string $value, int $line): void
    {
        if (!isset($this->enabled[$kind->value]) || '' === $value) {
            return;
        }

        $this->collected[] = new CollectedIdentifier($kind, $value, $line);
    }
}

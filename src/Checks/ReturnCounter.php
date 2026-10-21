<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

use Override;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

/**
 * Counts the return statements of a body, skipping nested functions,
 * closures, arrow functions, and class-likes.
 */
final class ReturnCounter extends NodeVisitorAbstract
{
    private int $count = 0;

    /**
     * @param  array<Node>  $statements
     */
    public static function count(array $statements): int
    {
        $counter = new self;
        new NodeTraverser($counter)->traverse($statements);

        return $counter->count;
    }

    #[Override]
    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Return_) {
            $this->count++;
        }

        if ($node instanceof FunctionLike || $node instanceof ClassLike) {
            return NodeVisitor::DONT_TRAVERSE_CHILDREN;
        }

        return null;
    }
}

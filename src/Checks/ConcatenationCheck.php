<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\AssignOp\Concat as ConcatAssign;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\NodeFinder;

/**
 * Any `.` or `.=` concatenation. A chain such as `$a . $b . $c` is reported
 * once, at its outermost operator. There are no exemptions.
 */
final class ConcatenationCheck implements Check
{
    #[Override]
    public function id(): string
    {
        return 'concat';
    }

    /**
     * @return list<Violation>
     */
    #[Override]
    public function check(SourceFile $file): array
    {
        $found = new NodeFinder()->find(
            $file->ast,
            static fn (Node $node): bool => $node instanceof Concat ||
                $node instanceof ConcatAssign,
        );

        $inner = [];

        foreach ($found as $node) {
            if ($node instanceof Concat) {
                $inner[spl_object_id($node->left)] = true;
                $inner[spl_object_id($node->right)] = true;
            }
        }

        $violations = [];

        foreach ($found as $node) {
            if (! isset($inner[spl_object_id($node)])) {
                $violations[] = new Violation(
                    $this->id(),
                    $file->path,
                    $node->getStartLine(),
                    'use string interpolation or sprintf() instead of concatenation.',
                );
            }
        }

        return $violations;
    }
}

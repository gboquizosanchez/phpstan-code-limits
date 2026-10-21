<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

use Override;
use PhpParser\Node\ClosureUse;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeFinder;

/**
 * A closure with two or more `use (...)` variables must list them in the order
 * of their first usage in the body (by-reference variables count like the
 * others). A variable the body never mentions counts as used last, and a
 * nested closure's own `use` list counts as a usage. Shadowing by a nested
 * closure's parameters is not modeled.
 */
final class ClosureUseOrderCheck implements Check
{
    #[Override]
    public function id(): string
    {
        return 'closureUseOrder';
    }

    /**
     * @return list<Violation>
     */
    #[Override]
    public function check(SourceFile $file): array
    {
        $violations = [];
        $closures = new NodeFinder()->findInstanceOf($file->ast, Closure::class);

        foreach ($closures as $closure) {
            if (count($closure->uses) < 2) {
                continue;
            }

            $expected = $this->expectedOrder($closure);

            if ($expected !== $this->declaredOrder($closure)) {
                $mappedVariables = array_map(
                    static fn (string $name): string => "\${$name}", $expected,
                );

                $violations[] = new Violation(
                    $this->id(),
                    $file->path,
                    $closure->getStartLine(),
                    sprintf(
                        'list the use() variables in the order of their first usage: %s.',
                        implode(', ', $mappedVariables),
                    ),
                );
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function declaredOrder(Closure $closure): array
    {
        return array_values(array_map(
            static function (ClosureUse $use): string {
                $name = $use->var->name;

                if (! is_string($name)) {
                    return '';
                }

                return $name;
            },
            $closure->uses,
        ));
    }

    /**
     * @return list<string>
     */
    private function expectedOrder(Closure $closure): array
    {
        $first = [];
        $variables = new NodeFinder()
            ->findInstanceOf($closure->stmts, Variable::class);

        foreach ($variables as $variable) {
            if (is_string($variable->name)) {
                $first[$variable->name] ??= $variable->getStartFilePos();
            }
        }

        $declared = $this->declaredOrder($closure);
        $position = array_flip($declared);

        // Stable sort: unused variables (PHP_INT_MAX) keep their declared order.
        usort(
            $declared,
            static fn (string $a, string $b): int => [
                $first[$a] ?? PHP_INT_MAX, $position[$a],
            ] <=> [
                $first[$b] ?? PHP_INT_MAX, $position[$b],
            ],
        );

        return $declared;
    }
}

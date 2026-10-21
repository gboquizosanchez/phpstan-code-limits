<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

use Override;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

/**
 * Destructuring (`[$a, $b] = ...` or `list(...) = ...`, positional or keyed)
 * of the result of one of the project's own methods: `$this->x()`,
 * `self::x()` or `static::x()` where `x` is declared in the SAME file with
 * return type `array` or no return type. Calls to anything else (`explode`,
 * `preg_*`, framework methods...) are never reported.
 *
 * Limitation: rules work per file, so a producer declared in another file (a
 * parent class, a trait) is not seen. Methods are matched by name only, not by
 * class, and `?array` is not treated as `array`.
 */
final class TupleDestructuringCheck implements Check
{
    #[Override]
    public function id(): string
    {
        return 'tuple';
    }

    /**
     * @return list<Violation>
     */
    #[Override]
    public function check(SourceFile $file): array
    {
        $finder = new NodeFinder;
        $producers = $this->arrayProducers($finder, $file);
        $violations = [];

        $assigns = $finder->findInstanceOf($file->ast, Assign::class);

        foreach ($assigns as $assign) {
            $isDestructuring = $assign->var instanceof Array_ ||
                $assign->var instanceof List_;
            $name = null;

            if ($isDestructuring) {
                $name = $this->ownCallName($assign);
            }

            if ($name !== null && in_array($name, $producers, true)) {
                $violations[] = new Violation(
                    $this->id(),
                    $file->path,
                    $assign->getStartLine(),
                    sprintf(
                        '%s() returns a tuple; return a small readonly value object instead.',
                        $name,
                    ),
                );
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function arrayProducers(
        NodeFinder $finder,
        SourceFile $file,
    ): array {
        $names = [];

        $methods = $finder->findInstanceOf($file->ast, ClassMethod::class);

        foreach ($methods as $method) {
            $type = $method->returnType;

            if ($type === null ||
                ($type instanceof Identifier && $type->toLowerString() === 'array')
            ) {
                $names[] = $method->name->toString();
            }
        }

        return $names;
    }

    private function ownCallName(Assign $assign): ?string
    {
        $call = $assign->expr;

        if ($call instanceof MethodCall) {
            $isOwn = $call->var instanceof Variable && $call->var->name === 'this';
        } elseif ($call instanceof StaticCall) {
            $isOwn = $call->class instanceof Name
                && in_array($call->class->toLowerString(), ['self', 'static'], true);
        } else {
            return null;
        }

        if (! $isOwn || ! $call->name instanceof Identifier) {
            return null;
        }

        return $call->name->toString();
    }
}

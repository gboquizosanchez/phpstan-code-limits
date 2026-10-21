<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

use Override;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;

/**
 * A closure or arrow function that is not `static` and does not use `$this`.
 *
 * "Uses `$this`" is any `$this` variable anywhere inside the closure,
 * including nested closures, which inherit the binding of the outer one.
 *
 * Exempt, because the framework binds them: closure arguments of calls named
 * `macro`, `afterCloned` and `command` (method or static); closure arguments
 * of `state` and `sequence` only in files under `database/factories/`; and
 * Pest bodies, i.e., closures passed directly to `test`, `it`, `describe`,
 * `beforeEach`, `afterEach`, `beforeAll`, `afterAll` and `dataset`.
 */
final class StaticClosureCheck implements Check
{
    private const array BOUND_CALLS = [
        'macro',
        'aftercloned',
        'command',
    ];

    private const array FACTORY_CALLS = [
        'state',
        'sequence',
    ];

    private const array PEST_FUNCTIONS = [
        'test',
        'it',
        'describe',
        'beforeeach',
        'aftereach',
        'beforeall',
        'afterall',
        'dataset',
    ];

    #[Override]
    public function id(): string
    {
        return 'closureStatic';
    }

    /**
     * @return list<Violation>
     */
    #[Override]
    public function check(SourceFile $file): array
    {
        new NodeTraverser(new ParentConnectingVisitor)->traverse($file->ast);

        $violations = [];

        $closures = new NodeFinder()->find($file->ast, $this->isClosure(...));

        foreach ($closures as $closure) {
            if ($closure instanceof Closure || $closure instanceof ArrowFunction) {
                if ($closure->static) {
                    continue;
                }

                if ($this->usesThis($closure)) {
                    continue;
                }

                if ($this->isBound($closure, $file)) {
                    continue;
                }

                $violations[] = new Violation(
                    $this->id(),
                    $file->path,
                    $closure->getStartLine(),
                    'make it static unless it uses $this.',
                );
            }
        }

        return $violations;
    }

    private function isClosure(Node $node): bool
    {
        return $node instanceof Closure || $node instanceof ArrowFunction;
    }

    private function usesThis(Closure|ArrowFunction $closure): bool
    {
        return new NodeFinder()->findFirst(
            $closure,
            static fn (Node $node): bool => $node instanceof Variable &&
                $node->name === 'this',
        ) instanceof Node;
    }

    private function isBound(
        Closure|ArrowFunction $closure,
        SourceFile $file,
    ): bool {
        $argument = $closure->getAttribute('parent');
        $call = null;
        $name = null;

        if ($argument instanceof Arg) {
            $call = $argument->getAttribute('parent');
        }

        if ($call instanceof CallLike) {
            $name = $this->callName($call);
        }

        if ($name === null) {
            return false;
        }

        if ($call instanceof FuncCall) {
            return in_array($name, self::PEST_FUNCTIONS, true);
        }

        return in_array($name, self::BOUND_CALLS, true)
            || (
                in_array($name, self::FACTORY_CALLS, true)
                && str_contains($file->path, 'database/factories/')
            );
    }

    private function callName(CallLike $call): ?string
    {
        if ($call instanceof FuncCall && $call->name instanceof Name) {
            return mb_strtolower($call->name->getLast());
        }

        if (
            ($call instanceof MethodCall
                || $call instanceof NullsafeMethodCall
                || $call instanceof StaticCall
            )
            && $call->name instanceof Identifier
        ) {
            return $call->name->toLowerString();
        }

        return null;
    }
}

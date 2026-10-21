<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

use LogicException;
use Override;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;

/**
 * HEURISTIC. Two or more nested call levels inside the arguments of an outer
 * call, unless the whole enclosing statement fits on a single line of at most
 * 80 characters (indentation included).
 *
 * Calls are function, method, static, and `new` expressions. The levels inside
 * the arguments of a call are the deepest chain of calls in them:
 * `outer(count($items))` has one level (fine), `outer(a(b()))` has two
 * (violation). A direct `sprintf()`, `__()` or `trans()` call does not count
 * as a level itself, but its own arguments do. Closure and arrow function
 * bodies are separate expressions and are not entered. The receiver of a
 * method chain is not part of the arguments. Reported once per outermost
 * offending call.
 *
 * The enclosing statement is the nearest statement node, collapsed to one
 * line; a compound statement (`if`, `foreach`...) is therefore usually longer
 * than 80 characters.
 */
final readonly class NestedArgumentsCheck implements Check
{
    private const array TRANSPARENT = [
        'sprintf',
        '__',
        'trans',
    ];

    public function __construct(
        private int $limit = 80,
    ) {}

    #[Override]
    public function id(): string
    {
        return 'nestedArgs';
    }

    /**
     * @return list<Violation>
     */
    #[Override]
    public function check(SourceFile $file): array
    {
        new NodeTraverser(new ParentConnectingVisitor)->traverse($file->ast);

        $finder = new NodeFinder;
        $offending = [];

        $calls = $finder->find($file->ast, $this->isCall(...));

        foreach ($calls as $call) {
            if ($call instanceof CallLike && $this->argumentLevels($call) >= 2) {
                $offending[] = $call;
            }
        }

        $inner = [];

        foreach ($offending as $call) {
            $nestedCalls = $finder->find($call->getArgs(), $this->isCall(...));

            foreach ($nestedCalls as $nested) {
                $inner[spl_object_id($nested)] = true;
            }
        }

        $violations = [];

        foreach ($offending as $call) {
            if (isset($inner[spl_object_id($call)])) {
                continue;
            }

            if ($this->statementFitsOnOneLine($file, $call)) {
                continue;
            }

            $violations[] = new Violation(
                $this->id(),
                $file->path,
                $call->getStartLine(),
                'two or more nested calls in the arguments, assign the inner call to a named variable first.',
            );
        }

        return $violations;
    }

    private function isCall(Node $node): bool
    {
        return $node instanceof CallLike && ! $node->isFirstClassCallable();
    }

    private function argumentLevels(CallLike $call): int
    {
        $levels = 0;

        foreach ($call->getArgs() as $arg) {
            $levels = max($levels, $this->levels($arg->value));
        }

        return $levels;
    }

    private function levels(Node $node): int
    {
        $isFirstClassCallable = $node instanceof CallLike
            && $node->isFirstClassCallable();

        if ($node instanceof FunctionLike || $isFirstClassCallable) {
            return 0;
        }

        if ($node instanceof CallLike) {
            return $this->callLevels($node);
        }

        $levels = 0;

        foreach ($node->getSubNodeNames() as $name) {
            $child = $node->{$name};

            $items = [$child];

            if (is_array($child)) {
                $items = $child;
            }

            foreach ($items as $item) {
                if ($item instanceof Node) {
                    $levels = max($levels, $this->levels($item));
                }
            }
        }

        return $levels;
    }

    private function callLevels(CallLike $call): int
    {
        $own = 1;
        $receiver = 0;

        if ($this->isTransparent($call)) {
            $own = 0;
        }

        if ($call instanceof MethodCall || $call instanceof NullsafeMethodCall) {
            $receiver = $this->levels($call->var);
        }

        return max($receiver, $own + $this->argumentLevels($call));
    }

    private function isTransparent(CallLike $call): bool
    {
        if (! $call instanceof FuncCall || ! $call->name instanceof Name) {
            return false;
        }

        $name = mb_strtolower($call->name->getLast());

        return in_array($name, self::TRANSPARENT, true);
    }

    private function statementFitsOnOneLine(
        SourceFile $file,
        CallLike $call,
    ): bool {
        $statement = $call->getAttribute('parent');

        while ($statement instanceof Node && ! $statement instanceof Stmt) {
            $statement = $statement->getAttribute('parent');
        }

        if (! $statement instanceof Stmt) {
            throw new LogicException('A call always sits in a statement.');
        }

        $lineStart = $file->lineStart($statement->getStartLine());
        $head = $file->slice($lineStart, $statement->getStartFilePos());
        $raw = $file->slice(
            $statement->getStartFilePos(),
            $statement->getEndFilePos() + 1,
        );
        $text = SourceFile::collapse($raw);

        return mb_strlen("{$head}{$text}") <= $this->limit;
    }
}

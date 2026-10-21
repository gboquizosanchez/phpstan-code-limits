<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\NodeFinder;

/**
 * The subject of a `foreach` is a plain variable, a property fetch (`$a->b`,
 * `$a?->b`, `A::$b`), a class constant (`A::B`, a deliberate extension of the
 * written rule) or ONE call (function, method, or static method) whose own
 * arguments contain no calls. The receiver of a method chain is not inspected,
 * so `$this->query()->get()` is one call for this rule. When the `foreach (...)
 * {` header written on one line is longer than 80 characters, a call subject
 * is reported. Anything else (ternary, `??`, operators, array literals, casts,
 * `new`, nested calls...) is a violation.
 */
final readonly class ForeachCheck implements Check
{
    public function __construct(
        private int $limit = 80,
    ) {}

    #[Override]
    public function id(): string
    {
        return 'foreach';
    }

    /**
     * @return list<Violation>
     */
    #[Override]
    public function check(SourceFile $file): array
    {
        $violations = [];

        $loops = new NodeFinder()->findInstanceOf($file->ast, Foreach_::class);

        foreach ($loops as $foreach) {
            $message = $this->problem($file, $foreach);

            if ($message !== null) {
                $violations[] = new Violation(
                    $this->id(),
                    $file->path,
                    $foreach->getStartLine(),
                    $message,
                );
            }
        }

        return $violations;
    }

    private function problem(SourceFile $file, Foreach_ $foreach): ?string
    {
        $subject = $foreach->expr;

        if (! $this->isAllowedSubject($subject)) {
            return 'iterate over a variable, a property or a single call without calls in its arguments; assign anything else to a named variable first.';
        }

        if (! $this->isCall($subject)) {
            return null;
        }

        return $this->headerProblem($file, $foreach);
    }

    private function headerProblem(
        SourceFile $file,
        Foreach_ $foreach,
    ): ?string {
        $length = $this->headerLength($file, $foreach);

        if ($length > $this->limit) {
            return sprintf(
                'the foreach header is %d characters on one line, assign the collection to a variable first.',
                $length,
            );
        }

        return null;
    }

    private function isAllowedSubject(Expr $subject): bool
    {
        if ($this->isVariableOrProperty($subject)) {
            return true;
        }

        return $this->isCall($subject)
            && $subject instanceof CallLike
            && ! $subject->isFirstClassCallable()
            && ! new NodeFinder()->findFirst(
                $subject->getArgs(),
                static fn (Node $node): bool => $node instanceof CallLike,
            ) instanceof Node;
    }

    private function isVariableOrProperty(Expr $subject): bool
    {
        return $subject instanceof Variable
            || $subject instanceof PropertyFetch
            || $subject instanceof NullsafePropertyFetch
            || $subject instanceof StaticPropertyFetch
            || $subject instanceof ClassConstFetch;
    }

    private function isCall(Expr $subject): bool
    {
        return $subject instanceof FuncCall
            || $subject instanceof MethodCall
            || $subject instanceof NullsafeMethodCall
            || $subject instanceof StaticCall;
    }

    private function headerLength(SourceFile $file, Foreach_ $foreach): int
    {
        $assigned = $foreach->valueVar;
        $end = max(
            $assigned->getEndFilePos(),
            $foreach->keyVar?->getEndFilePos() ?? 0,
        );
        $close = mb_strpos($file->code, ')', $end + 1, '8bit');
        $lineStart = $file->lineStart($foreach->getStartLine());
        $head = $file->slice($lineStart, $foreach->getStartFilePos());

        if ($close === false) {
            $close = $end;
        }

        $raw = $file->slice($foreach->getStartFilePos(), $close + 1);
        $header = SourceFile::collapse($raw);

        return mb_strlen(sprintf('%s%s {', $head, $header));
    }
}

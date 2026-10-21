<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

use Override;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;

/**
 * Layout and arguments of `throw_if()` / `throw_unless()` calls (functions,
 * not methods).
 *
 * Layout: the statement written on one line (everything before the call on its
 * line, the call and the `;`) is measured in characters. Up to 80 it must be
 * written on one line; longer, one argument per line with a trailing comma and
 * the closing parenthesis alone on its line.
 *
 * Arguments: the condition (first) and the message (third, when present) must
 * be inline expressions, not a bare variable.
 */
final readonly class ThrowCheck implements Check
{
    public function __construct(
        private int $limit = 80,
    ) {}

    #[Override]
    public function id(): string
    {
        return 'throw';
    }

    /**
     * @return list<Violation>
     */
    #[Override]
    public function check(SourceFile $file): array
    {
        $violations = [];

        $calls = new NodeFinder()->findInstanceOf($file->ast, FuncCall::class);

        foreach ($calls as $call) {
            $name = '';

            if ($call->name instanceof Name) {
                $name = $call->name->getLast();
            }

            $allowedThrowFunctions = [
                'throw_if',
                'throw_unless',
            ];

            if (! in_array(mb_strtolower($name), $allowedThrowFunctions, true)) {
                continue;
            }

            if ($call->isFirstClassCallable()) {
                continue;
            }

            foreach ($this->problems($file, $call, $name) as $problem) {
                $violations[] = new Violation(
                    $this->id(),
                    $file->path,
                    $call->getStartLine(),
                    $problem,
                );
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function problems(
        SourceFile $file,
        FuncCall $call,
        string $name,
    ): array {
        $args = array_values($call->getArgs());
        $problems = [];

        if ($args === []) {
            return $problems;
        }

        $layout = $this->layoutProblem($file, $call, $args);

        if ($layout !== null) {
            $problems[] = sprintf('%s(): %s', $name, $layout);
        }

        if ($args[0]->value instanceof Variable) {
            $problems[] = sprintf(
                '%s(): the condition must be an inline expression, not a variable.',
                $name,
            );
        }

        if (isset($args[2]) && $args[2]->value instanceof Variable) {
            $problems[] = sprintf(
                '%s(): the message must be an inline expression, not a variable.',
                $name,
            );
        }

        return $problems;
    }

    /**
     * @param  non-empty-list<Arg>  $args
     */
    private function layoutProblem(
        SourceFile $file,
        FuncCall $call,
        array $args,
    ): ?string {
        $length = $this->oneLineLength($file, $call, $args);

        $fits = $length <= $this->limit;

        if ($fits && $call->getStartLine() !== $call->getEndLine()) {
            return sprintf(
                'the statement fits in %d characters, write it on one line.',
                $length,
            );
        }

        if (! $fits && ! $this->isOnePerLine($file, $call, $args)) {
            return sprintf(
                'the statement is %d characters on one line, write one argument per line with a trailing comma.',
                $length,
            );
        }

        return null;
    }

    /**
     * @param  non-empty-list<Arg>  $args
     */
    private function oneLineLength(
        SourceFile $file,
        FuncCall $call,
        array $args,
    ): int {
        $lineStart = $file->lineStart($call->getStartLine());
        $head = $file->slice($lineStart, $call->name->getStartFilePos());
        $name = $file->slice(
            $call->name->getStartFilePos(),
            $call->name->getEndFilePos() + 1,
        );
        $arguments = array_map(
            static function (Arg $arg) use ($file): string {
                $raw = $file->slice(
                    $arg->getStartFilePos(),
                    $arg->getEndFilePos() + 1,
                );

                return SourceFile::collapse($raw);
            },
            $args,
        );

        return mb_strlen(
            sprintf('%s%s(%s);', $head, $name, implode(', ', $arguments)),
        );
    }

    /**
     * @param  non-empty-list<Arg>  $args
     */
    private function isOnePerLine(
        SourceFile $file,
        FuncCall $call,
        array $args,
    ): bool {
        $previousEnd = $call->getStartLine();

        foreach ($args as $arg) {
            if ($arg->getStartLine() <= $previousEnd) {
                return false;
            }

            $previousEnd = $arg->getEndLine();
        }

        $tail = $file->slice(
            $args[count($args) - 1]->getEndFilePos() + 1,
            $call->getEndFilePos() + 1
        );

        return preg_match('/^\s*,[ \t\r]*\n\s*\)$/', $tail) === 1;
    }
}

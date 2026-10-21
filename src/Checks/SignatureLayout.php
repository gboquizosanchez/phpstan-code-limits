<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

use LogicException;
use PhpParser\Node\Param;

/**
 * How the parameters of a function declaration are written, and how long the
 * signature would be if it were written on ONE line.
 *
 * Measuring convention: the one-line signature starts at the beginning of the
 * line holding the function name (so the indentation counts and modifiers must
 * share that line) and ends at the first `{` or `;` after the last parameter
 * (inclusive), with every line break collapsed to a single space and no
 * trailing comma. A brace written on its own line is therefore measured as
 * ` {` appended to the signature. Characters are counted, not bytes.
 *
 * A signature is "one line" when the first parameter, the last parameter, and
 * the closing parenthesis all sit on the name line (the brace may be below). It is "one per line" when the first
 * parameter starts below the name line, no two parameters share a line, the
 * last parameter is followed by a trailing comma, and the closing parenthesis
 * is alone on the line after it.
 */
final readonly class SignatureLayout
{
    public function __construct(
        public int $oneLineLength,
        public bool $isOneLine,
        public bool $isOnePerLine,
    ) {}

    public static function of(
        SourceFile $file,
        FunctionDeclaration $function,
    ): self {
        $params = $function->node->params;
        $first = $params[0];
        $last = $params[count($params) - 1];
        $nameLine = $function->line();

        $lineStart = $file->lineStart($nameLine);
        $afterLast = $last->getEndFilePos() + 1;
        $tail = $file->slice(
            $afterLast,
            self::bodyOpener($file, $afterLast) + 1,
        );

        $head = mb_rtrim($file->slice($lineStart, $first->getStartFilePos()));
        $parameters = array_map(
            static function (Param $param) use ($file): string {
                $raw = $file->slice(
                    $param->getStartFilePos(),
                    $param->getEndFilePos() + 1,
                );

                return SourceFile::collapse($raw);
            },
            $params,
        );
        $closing = SourceFile::collapse(mb_ltrim($tail, ", \t\r\n"));
        $oneLine = sprintf('%s%s%s', $head, implode(', ', $parameters), $closing);

        return new self(
            mb_strlen($oneLine),
            $first->getStartLine() === $nameLine
                && $last->getEndLine() === $nameLine
                && preg_match('/^[ \t]*\)/', $tail) === 1,
            $first->getStartLine() > $nameLine
                && self::paramsDoNotShareLines($params)
                && preg_match('/^\s*,[ \t\r]*\n\s*\)/', $tail) === 1,
        );
    }

    /**
     * @param  array<Param>  $params
     */
    private static function paramsDoNotShareLines(array $params): bool
    {
        $previousEnd = null;

        foreach ($params as $param) {
            if ($previousEnd !== null && $param->getStartLine() <= $previousEnd) {
                return false;
            }

            $previousEnd = $param->getEndLine();
        }

        return true;
    }

    private static function bodyOpener(SourceFile $file, int $from): int
    {
        $found = preg_match('/[{;]/', $file->code, $matches, PREG_OFFSET_CAPTURE, $from);

        if ($found !== 1) {
            throw new LogicException('Function has no body opener.');
        }

        return $matches[0][1];
    }
}

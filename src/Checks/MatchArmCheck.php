<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

use Override;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\MatchArm;
use PhpParser\NodeFinder;

/**
 * A `match` arm with several conditions puts each condition on its own line
 * and the `=> result` on the last condition's line. The commas between the
 * conditions are required by the syntax, so a comma after the last condition
 * is not checked. Arms with one condition and the `default` arm are not
 * covered.
 */
final class MatchArmCheck implements Check
{
    #[Override]
    public function id(): string
    {
        return 'matchArm';
    }

    /**
     * @return list<Violation>
     */
    #[Override]
    public function check(SourceFile $file): array
    {
        $violations = [];

        $matches = new NodeFinder()->findInstanceOf($file->ast, Match_::class);

        foreach ($matches as $match) {
            foreach ($match->arms as $arm) {
                $problem = $this->problem($file, $arm);

                if ($problem !== null) {
                    $violations[] = new Violation(
                        $this->id(),
                        $file->path,
                        $arm->getStartLine(),
                        $problem,
                    );
                }
            }
        }

        return $violations;
    }

    private function problem(SourceFile $file, MatchArm $arm): ?string
    {
        $conditions = $arm->conds;

        if ($conditions === null || count($conditions) < 2) {
            return null;
        }

        $previousEnd = 0;

        foreach ($conditions as $condition) {
            if ($condition->getStartLine() <= $previousEnd) {
                return 'write each condition of the arm on its own line.';
            }

            $previousEnd = $condition->getEndLine();
        }

        return $this->arrowProblem($file, $arm);
    }

    private function arrowProblem(SourceFile $file, MatchArm $arm): ?string
    {
        $conditions = $arm->conds ?? [];
        $last = $conditions[count($conditions) - 1];
        $between = $file->slice(
            $last->getEndFilePos() + 1,
            $arm->body->getStartFilePos(),
        );
        $beforeArrow = mb_strstr($between, '=>', true, '8bit');

        if ($beforeArrow !== false && str_contains($beforeArrow, "\n")) {
            return 'write the => on the line of the last condition.';
        }

        return null;
    }
}

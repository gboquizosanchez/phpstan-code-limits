<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

use Override;

/**
 * More than 3 return statements in a function or method, counting only its
 * own returns (not those of nested closures, arrow functions, functions or
 * anonymous classes).
 */
final readonly class ReturnCountCheck implements Check
{
    public function __construct(
        private int $limit = 3,
    ) {}

    #[Override]
    public function id(): string
    {
        return 'returns';
    }

    /**
     * @return list<Violation>
     */
    #[Override]
    public function check(SourceFile $file): array
    {
        $violations = [];

        foreach (DeclarationFinder::find($file) as $function) {
            $count = ReturnCounter::count($function->node->stmts ?? []);

            if ($count > $this->limit) {
                $violations[] = new Violation(
                    $this->id(),
                    $file->path,
                    $function->line(),
                    sprintf(
                        '%s() has %d return statements, the limit is %d.',
                        $function->name(),
                        $count,
                        $this->limit,
                    ),
                );
            }
        }

        return $violations;
    }
}

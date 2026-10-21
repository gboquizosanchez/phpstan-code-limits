<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

use Override;
use PhpParser\Node\Param;

/**
 * More than 3 parameters in a function or method. Promoted parameters of a
 * constructor in a readonly class (a value object) do not count. Closures and
 * arrow functions are not covered.
 */
final readonly class ParameterCountCheck implements Check
{
    public function __construct(
        private int $limit = 3,
    ) {}

    #[Override]
    public function id(): string
    {
        return 'params';
    }

    /**
     * @return list<Violation>
     */
    #[Override]
    public function check(SourceFile $file): array
    {
        $violations = [];

        foreach (DeclarationFinder::find($file) as $function) {
            $count = $this->countedParameters($function);

            if ($count > $this->limit) {
                $violations[] = new Violation(
                    $this->id(),
                    $file->path,
                    $function->line(),
                    sprintf(
                        '%s() has %d parameters, the limit is %d.',
                        $function->name(),
                        $count,
                        $this->limit,
                    ),
                );
            }
        }

        return $violations;
    }

    private function countedParameters(FunctionDeclaration $function): int
    {
        $exempt = $function->isConstructor() && $function->inReadonlyClass;

        return count(array_filter(
            $function->node->params,
            static fn (Param $param): bool => ! $exempt || ! $param->isPromoted(),
        ));
    }
}

<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

use Override;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;

/**
 * More than 20 methods in a class, trait, interface, enum, or anonymous class.
 */
final readonly class MethodCountCheck implements Check
{
    public function __construct(
        private int $limit = 20,
    ) {}

    #[Override]
    public function id(): string
    {
        return 'methods';
    }

    /**
     * @return list<Violation>
     */
    #[Override]
    public function check(SourceFile $file): array
    {
        $violations = [];

        $classes = new NodeFinder()
            ->findInstanceOf($file->ast, ClassLike::class);

        foreach ($classes as $class) {
            $count = count($class->getMethods());

            if ($count > $this->limit) {
                $violations[] = new Violation(
                    $this->id(),
                    $file->path,
                    $class->getStartLine(),
                    sprintf(
                        '%s has %d methods, the limit is %d.',
                        $class->name?->toString() ?? 'Anonymous class',
                        $count,
                        $this->limit,
                    ),
                );
            }
        }

        return $violations;
    }
}

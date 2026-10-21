<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

use Override;

/**
 * A constructor with at least one promoted parameter: one parameter per line
 * with a trailing comma, even when it fits in 80 characters.
 */
final class PromotedConstructorCheck implements Check
{
    #[Override]
    public function id(): string
    {
        return 'promotedCtor';
    }

    /**
     * @return list<Violation>
     */
    #[Override]
    public function check(SourceFile $file): array
    {
        $violations = [];

        foreach (DeclarationFinder::find($file) as $function) {
            if (! $function->isConstructor()) {
                continue;
            }

            if (! $function->hasPromotedParameter()) {
                continue;
            }

            if (! SignatureLayout::of($file, $function)->isOnePerLine) {
                $violations[] = new Violation(
                    $this->id(),
                    $file->path,
                    $function->line(),
                    'constructor with promoted properties must have one parameter per line with a trailing comma.',
                );
            }
        }

        return $violations;
    }
}

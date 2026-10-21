<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

use Override;

/**
 * Layout of a function or method signature (see SignatureLayout for how the
 * length is measured). Longer than 80 characters when written on one line: one
 * parameter per line with a trailing comma. 80 or less: on one line. Promoted
 * constructors are left to PromotedConstructorCheck.
 */
final readonly class SignatureCheck implements Check
{
    public function __construct(
        private int $limit = 80,
    ) {}

    #[Override]
    public function id(): string
    {
        return 'signature';
    }

    /**
     * @return list<Violation>
     */
    #[Override]
    public function check(SourceFile $file): array
    {
        $violations = [];

        foreach (DeclarationFinder::find($file) as $function) {
            $message = $this->problem($file, $function);

            if ($message !== null) {
                $violations[] = new Violation(
                    $this->id(),
                    $file->path,
                    $function->line(),
                    sprintf('%s(): %s', $function->name(), $message),
                );
            }
        }

        return $violations;
    }

    private function problem(
        SourceFile $file,
        FunctionDeclaration $function,
    ): ?string {
        if ($function->node->params === [] || $function->hasPromotedParameter()) {
            return null;
        }

        return $this->layoutProblem(SignatureLayout::of($file, $function));
    }

    private function layoutProblem(SignatureLayout $layout): ?string
    {
        $tooLong = $layout->oneLineLength > $this->limit;

        if ($tooLong && ! $layout->isOnePerLine) {
            return sprintf(
                'signature is %d characters on one line, write one parameter per line with a trailing comma.',
                $layout->oneLineLength,
            );
        }

        if (! $tooLong && ! $layout->isOneLine) {
            return sprintf(
                'signature fits in %d characters, write it on one line.',
                $layout->oneLineLength,
            );
        }

        return null;
    }
}

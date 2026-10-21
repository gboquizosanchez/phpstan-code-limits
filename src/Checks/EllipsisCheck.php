<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

use Override;

/**
 * The single character U+2026 anywhere in the file (code, comments, strings).
 * Each line is reported once.
 */
final class EllipsisCheck implements Check
{
    #[Override]
    public function id(): string
    {
        return 'ellipsis';
    }

    /**
     * @return list<Violation>
     */
    #[Override]
    public function check(SourceFile $file): array
    {
        $violations = [];

        foreach ($file->lines as $index => $line) {
            if (str_contains($line, "\u{2026}")) {
                $violations[] = new Violation(
                    $this->id(),
                    $file->path,
                    $index + 1,
                    'use three ASCII dots "..." instead of the ellipsis character.',
                );
            }
        }

        return $violations;
    }
}

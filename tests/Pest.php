<?php

declare(strict_types=1);

use Boquizo\PHPStanCodeLimits\Checks\Check;
use Boquizo\PHPStanCodeLimits\Checks\SourceFile;
use Boquizo\PHPStanCodeLimits\Checks\Violation;

/**
 * Runs one check over a source string, its violations sorted by line.
 *
 * @return list<Violation>
 */
function scan(Check $check, string $path, string $code): array
{
    $violations = $check->check(SourceFile::parse($path, $code));

    usort(
        $violations,
        static fn (Violation $a, Violation $b): int => $a->line <=> $b->line,
    );

    return $violations;
}

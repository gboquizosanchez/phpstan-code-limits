<?php

declare(strict_types=1);

use Boquizo\PHPStanCodeLimits\Checks\ConcatenationCheck;
use Boquizo\PHPStanCodeLimits\Checks\Violation;

/**
 * @return list<int>
 */
function concatLines(string $code): array
{
    $violations = scan(new ConcatenationCheck, 'x.php', $code);

    return array_map(static fn (Violation $violation): int => $violation->line, $violations);
}

test('the rule id is concat', function (): void {
    expect(new ConcatenationCheck()->id())->toBe('concat');
});

test('a concatenation is reported with a hint', function (): void {
    $violations = scan(new ConcatenationCheck, 'x.php', "<?php\n\$c = \$a . \$b;\n");

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->rule)->toBe('concat')
        ->and($violations[0]->line)->toBe(2)
        ->and($violations[0]->message)->toBe('use string interpolation or sprintf() instead of concatenation.');
});

test('the concatenating assignment is reported', function (): void {
    expect(concatLines("<?php\n\$c = '';\n\$c .= \$b;\n"))->toBe([3]);
});

test('a chain is reported once', function (): void {
    expect(concatLines("<?php\n\$c = \$a . \$b . \$c . \$d;\n"))->toBe([2]);
});

test('a concatenation nested in a call is reported separately', function (): void {
    expect(concatLines("<?php\n\$c = \$a . strtoupper(\$b . \$c);\n"))->toBe([2, 2]);
});

test('code without concatenation and a plain assignment are clean', function (): void {
    expect(concatLines("<?php\n\$c = \"{\$a} {\$b}\";\n\$d = sprintf('%s', \$c);\n\$e += 1;\n"))->toBe([]);
});

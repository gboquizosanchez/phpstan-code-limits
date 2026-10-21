<?php

declare(strict_types=1);

use Boquizo\PHPStanCodeLimits\Checks\EllipsisCheck;
use Boquizo\PHPStanCodeLimits\Checks\Violation;

/**
 * @return list<Violation>
 */
function ellipsisViolations(string $code): array
{
    return scan(new EllipsisCheck, 'x.php', $code);
}

test('the rule id is ellipsis', function (): void {
    expect(new EllipsisCheck()->id())->toBe('ellipsis');
});

test('the character is reported in strings and comments, once per line', function (): void {
    $dots = "\u{2026}";
    $code = "<?php\n// wait{$dots}\n\$a = 'x{$dots}y{$dots}';\n\$b = 1;\n";

    $violations = ellipsisViolations($code);

    expect($violations)->toHaveCount(2)
        ->and($violations[0]->rule)->toBe('ellipsis')
        ->and($violations[0]->line)->toBe(2)
        ->and($violations[1]->line)->toBe(3)
        ->and($violations[0]->message)->toBe('use three ASCII dots "..." instead of the ellipsis character.');
});

test('three ASCII dots are fine', function (): void {
    expect(ellipsisViolations("<?php\n// wait...\n\$a = 'x...';\n"))->toBe([]);
});

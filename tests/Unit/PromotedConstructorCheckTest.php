<?php

declare(strict_types=1);

use Boquizo\PHPStanCodeLimits\Checks\PromotedConstructorCheck;
use Boquizo\PHPStanCodeLimits\Checks\Violation;

/**
 * @return list<Violation>
 */
function promotedViolations(string $code): array
{
    return scan(new PromotedConstructorCheck, 'x.php', $code);
}

test('the rule id is promotedCtor', function (): void {
    expect(new PromotedConstructorCheck()->id())->toBe('promotedCtor');
});

test('a promoted constructor on one line is reported even when short', function (): void {
    $code = <<<'PHP'
<?php
final class A
{
    public function __construct(private int $a) {}
}
PHP;

    $violations = promotedViolations($code);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->rule)->toBe('promotedCtor')
        ->and($violations[0]->line)->toBe(4)
        ->and($violations[0]->message)->toBe(
            'constructor with promoted properties must have one parameter per line with a trailing comma.',
        );
});

test('a promoted constructor with one parameter per line and a trailing comma is fine', function (): void {
    $code = <<<'PHP'
<?php
final class A
{
    public function __construct(
        private int $a,
        string $b,
    ) {}
}
PHP;

    expect(promotedViolations($code))->toBe([]);
});

test('a promoted constructor without a trailing comma is reported', function (): void {
    $code = <<<'PHP'
<?php
final class A
{
    public function __construct(
        private int $a
    ) {}
}
PHP;

    expect(promotedViolations($code))->toHaveCount(1);
});

test('constructors without promoted parameters and other methods are not covered', function (): void {
    $code = <<<'PHP'
<?php
final class A
{
    public function __construct(int $a) {}
    public function __construct2(private int $a) {}
}
function __construct(int $a): void {}
PHP;

    expect(promotedViolations($code))->toBe([]);
});

test('a constructor without parameters is not covered', function (): void {
    $code = <<<'PHP'
<?php
final class A
{
    public function __construct() {}
}
PHP;

    expect(promotedViolations($code))->toBe([]);
});

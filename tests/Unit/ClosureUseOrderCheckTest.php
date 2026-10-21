<?php

declare(strict_types=1);

use Boquizo\PHPStanCodeLimits\Checks\ClosureUseOrderCheck;
use Boquizo\PHPStanCodeLimits\Checks\Violation;

/**
 * @return list<Violation>
 */
function useOrderViolations(string $code): array
{
    return scan(new ClosureUseOrderCheck, 'x.php', $code);
}

test('the rule id is closureUseOrder', function (): void {
    expect(new ClosureUseOrderCheck()->id())->toBe('closureUseOrder');
});

test('variables listed against their first usage are reported with the expected order', function (): void {
    $code = <<<'PHP'
        <?php
        $f = static function () use ($b, $a): int {
            return $a + $b;
        };
        PHP;

    $violations = useOrderViolations($code);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->rule)->toBe('closureUseOrder')
        ->and($violations[0]->line)->toBe(2)
        ->and($violations[0]->message)->toBe('list the use() variables in the order of their first usage: $a, $b.');
});

test('variables listed in order of first usage are clean', function (): void {
    $code = <<<'PHP'
        <?php
        $f = static function () use ($a, $b): int {
            return $a + $b + $a;
        };
        PHP;

    expect(useOrderViolations($code))->toBe([]);
});

test('only the first usage counts', function (): void {
    $code = <<<'PHP'
        <?php
        $f = static function () use ($a, $b): int {
            $b = $b + 1;
            return $a + $b;
        };
        PHP;

    expect(useOrderViolations($code))->toHaveCount(1);
});

test('by-reference variables count like the others', function (): void {
    $code = <<<'PHP'
        <?php
        $bad = static function () use (&$b, $a): void {
            $a = 1;
            $b = 2;
        };
        $good = static function () use (&$a, $b): void {
            $a = 1;
            $b = 2;
        };
        PHP;

    $violations = useOrderViolations($code);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->line)->toBe(2);
});

test('closures with fewer than two variables are not checked', function (): void {
    $code = <<<'PHP'
        <?php
        $f = static function () use ($a): int {
            return $a;
        };
        $g = static function (): int {
            return 1;
        };
        $h = fn (int $a, int $b): int => $b + $a;
        PHP;

    expect(useOrderViolations($code))->toBe([]);
});

test('unused variables count as used last and keep their order', function (): void {
    $code = <<<'PHP'
        <?php
        $good = static function () use ($b, $x, $y): int {
            return $b;
        };
        $bad = static function () use ($x, $b): int {
            return $b;
        };
        PHP;

    $violations = useOrderViolations($code);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->line)->toBe(5)
        ->and($violations[0]->message)->toContain('$b, $x');
});

test('a nested closure use list counts as a usage', function (): void {
    $code = <<<'PHP'
        <?php
        $f = static function () use ($a, $b): Closure {
            return static function () use ($b, $a): int {
                return $b + $a;
            };
        };
        PHP;

    $violations = useOrderViolations($code);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->line)->toBe(2);
});

<?php

declare(strict_types=1);

use Boquizo\PHPStanCodeLimits\Checks\MatchArmCheck;
use Boquizo\PHPStanCodeLimits\Checks\Violation;

/**
 * @return list<Violation>
 */
function matchArmViolations(string $code): array
{
    return scan(new MatchArmCheck, 'x.php', $code);
}

test('the rule id is matchArm', function (): void {
    expect(new MatchArmCheck()->id())->toBe('matchArm');
});

test('several conditions on one line are reported with a hint', function (): void {
    $code = <<<'PHP'
        <?php
        $r = match ($x) {
            1 => 'a',
            2, 3 => 'b',
            default => 'c',
        };
        PHP;

    $violations = matchArmViolations($code);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->rule)->toBe('matchArm')
        ->and($violations[0]->line)->toBe(4)
        ->and($violations[0]->message)->toBe('write each condition of the arm on its own line.');
});

test('one condition per line with the arrow on the last one is clean', function (): void {
    $code = <<<'PHP'
        <?php
        $r = match ($x) {
            1,
            2,
            3 => 'a',
            4,
            5, => 'b',
            default => 'c',
        };
        PHP;

    expect(matchArmViolations($code))->toBe([]);
});

test('an arrow below the last condition is reported', function (): void {
    $code = <<<'PHP'
        <?php
        $r = match ($x) {
            1,
            2
                => 'a',
            default => 'c',
        };
        PHP;

    $violations = matchArmViolations($code);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->line)->toBe(3)
        ->and($violations[0]->message)->toBe('write the => on the line of the last condition.');
});

test('arms with one condition and the default arm are not covered', function (): void {
    $code = <<<'PHP'
        <?php
        $r = match (true) {
            $a > 1 => 'a',
            $b,
                => 'b',
            default => 'c',
        };
        PHP;

    expect(matchArmViolations($code))->toBe([]);
});

test('only the first problem of an arm is reported', function (): void {
    $code = <<<'PHP'
        <?php
        $r = match ($x) {
            1, 2
                => 'a',
        };
        PHP;

    expect(matchArmViolations($code))->toHaveCount(1);
});

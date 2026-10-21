<?php

declare(strict_types=1);

use Boquizo\PHPStanCodeLimits\Checks\ReturnCountCheck;
use Boquizo\PHPStanCodeLimits\Checks\Violation;

/**
 * @return list<int>
 */
function returnsLines(string $code): array
{
    $violations = scan(new ReturnCountCheck, 'x.php', $code);

    return array_map(static fn (Violation $violation): int => $violation->line, $violations);
}

test('the rule id is returns', function (): void {
    expect(new ReturnCountCheck()->id())->toBe('returns');
});

test('three returns are allowed and four are reported', function (): void {
    $code = <<<'PHP'
<?php
function ok(int $a): int
{
    if ($a === 1) {
        return 1;
    }
    if ($a === 2) {
        return 2;
    }

    return 3;
}
function bad(int $a): int
{
    if ($a === 1) {
        return 1;
    }
    if ($a === 2) {
        return 2;
    }
    if ($a === 3) {
        return 3;
    }

    return 4;
}
PHP;

    expect(returnsLines($code))->toBe([13]);
});

test('the message names the method and the count', function (): void {
    $code = <<<'PHP'
<?php
final class A
{
    public function bad(int $a): int
    {
        if ($a === 1) { return 1; }
        if ($a === 2) { return 2; }
        if ($a === 3) { return 3; }
        return 4;
    }
}
PHP;

    $violations = scan(new ReturnCountCheck, 'a.php', $code);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->message)->toBe('bad() has 4 return statements, the limit is 3.');
});

test('returns of nested closures, arrow functions, functions and anonymous classes are not counted', function (): void {
    $code = <<<'PHP'
<?php
function outer(): int
{
    $a = function (): int { return 1; };
    $b = fn (): int => 2;
    $c = new class {
        public function m(): int { return 3; }
    };
    function inner(): int { return 4; }

    return 5;
}
PHP;

    expect(returnsLines($code))->toBe([]);
});

test('abstract and interface methods have no returns', function (): void {
    $code = <<<'PHP'
<?php
interface I
{
    public function m(int $a): int;
}
abstract class B
{
    abstract public function n(): int;
}
PHP;

    expect(returnsLines($code))->toBe([]);
});

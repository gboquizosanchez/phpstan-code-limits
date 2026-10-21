<?php

declare(strict_types=1);

use Boquizo\PHPStanCodeLimits\Checks\ParameterCountCheck;
use Boquizo\PHPStanCodeLimits\Checks\Violation;

/**
 * @return list<int>
 */
function paramsLines(string $code): array
{
    $violations = scan(new ParameterCountCheck, 'x.php', $code);

    return array_map(static fn (Violation $violation): int => $violation->line, $violations);
}

test('the rule id is params', function (): void {
    expect(new ParameterCountCheck()->id())->toBe('params');
});

test('three parameters are allowed and four are reported', function (): void {
    $code = <<<'PHP'
<?php
function ok(int $a, int $b, int $c): void {}
function bad(int $a, int $b, int $c, int $d): void {}
PHP;

    expect(paramsLines($code))->toBe([3]);
});

test('methods are checked and the message names the method', function (): void {
    $code = <<<'PHP'
<?php
final class A
{
    public function bad(int $a, int $b, int $c, int $d): void {}
}
PHP;

    $violations = scan(new ParameterCountCheck, 'a.php', $code);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->rule)->toBe('params')
        ->and($violations[0]->file)->toBe('a.php')
        ->and($violations[0]->line)->toBe(4)
        ->and($violations[0]->message)->toBe('bad() has 4 parameters, the limit is 3.');
});

test('promoted parameters of a readonly class constructor do not count', function (): void {
    $code = <<<'PHP'
<?php
final readonly class Vo
{
    public function __construct(
        public int $a,
        public int $b,
        public int $c,
        public int $d,
    ) {}
}
PHP;

    expect(paramsLines($code))->toBe([]);
});

test('plain parameters of a readonly class constructor still count', function (): void {
    $code = <<<'PHP'
<?php
final readonly class Vo
{
    public function __construct(int $a, int $b, int $c, int $d, public int $e) {}
}
PHP;

    expect(paramsLines($code))->toBe([4]);
});

test('promoted parameters of a non readonly class count', function (): void {
    $code = <<<'PHP'
<?php
final class NotVo
{
    public function __construct(public int $a, public int $b, public int $c, public int $d) {}
}
PHP;

    expect(paramsLines($code))->toBe([4]);
});

test('a readonly class does not exempt other methods', function (): void {
    $code = <<<'PHP'
<?php
final readonly class Vo
{
    public function make(int $a, int $b, int $c, int $d): void {}
}
PHP;

    expect(paramsLines($code))->toBe([4]);
});

test('closures and arrow functions are not covered', function (): void {
    $code = <<<'PHP'
<?php
$a = function (int $a, int $b, int $c, int $d): void {};
$b = fn (int $a, int $b, int $c, int $d): int => $a;
PHP;

    expect(paramsLines($code))->toBe([]);
});

<?php

declare(strict_types=1);

use Boquizo\PHPStanCodeLimits\Checks\StaticClosureCheck;
use Boquizo\PHPStanCodeLimits\Checks\Violation;

/**
 * @return list<int>
 */
function staticClosureLines(string $code, string $path = 'app/x.php'): array
{
    $violations = scan(new StaticClosureCheck, $path, $code);

    return array_map(static fn (Violation $violation): int => $violation->line, $violations);
}

test('the rule id is closureStatic', function (): void {
    expect(new StaticClosureCheck()->id())->toBe('closureStatic');
});

test('a non-static closure that does not use $this is reported with a hint', function (): void {
    $code = <<<'PHP'
        <?php
        $f = function (int $a): int {
            return $a;
        };
        PHP;

    $violations = scan(new StaticClosureCheck, 'app/x.php', $code);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->rule)->toBe('closureStatic')
        ->and($violations[0]->line)->toBe(2)
        ->and($violations[0]->message)->toBe('make it static unless it uses $this.');
});

test('a non-static arrow function is reported', function (): void {
    $code = <<<'PHP'
        <?php
        $f = fn (int $a): int => $a;
        PHP;

    expect(staticClosureLines($code))->toBe([2]);
});

test('static closures and static arrow functions are clean', function (): void {
    $code = <<<'PHP'
        <?php
        $f = static function (int $a): int {
            return $a;
        };
        $g = static fn (int $a): int => $a;
        PHP;

    expect(staticClosureLines($code))->toBe([]);
});

test('closures that use $this are clean, nested ones included', function (): void {
    $code = <<<'PHP'
        <?php
        $f = function (): int {
            return $this->a;
        };
        $g = fn (): int => $this->a;
        $h = function (): Closure {
            return fn (): int => $this->a;
        };
        PHP;

    expect(staticClosureLines($code))->toBe([]);
});

test('closure arguments of macro, afterCloned and command calls are exempt', function (): void {
    $code = <<<'PHP'
        <?php
        Builder::macro('a', function (): int {
            return 1;
        });
        $factory->afterCloned(fn (): int => 1);
        Artisan::command('b', function (): int {
            return 1;
        });
        $kernel?->command('c', fn (): int => 1);
        PHP;

    expect(staticClosureLines($code))->toBe([]);
});

test('state and sequence closures are exempt only in factories', function (): void {
    $code = <<<'PHP'
        <?php
        $factory->state(fn (): array => []);
        $factory->sequence(fn (): array => []);
        PHP;

    expect(staticClosureLines($code, 'database/factories/UserFactory.php'))->toBe([])
        ->and(staticClosureLines($code, 'app/Models/User.php'))->toBe([2, 3]);
});

test('Pest bodies are exempt', function (): void {
    $code = <<<'PHP'
        <?php
        test('a', function (): void {});
        it('b', fn (): bool => true);
        describe('c', function (): void {});
        beforeEach(function (): void {});
        afterEach(function (): void {});
        beforeAll(function (): void {});
        afterAll(function (): void {});
        dataset('d', fn (): array => []);
        PHP;

    expect(staticClosureLines($code))->toBe([]);
});

test('other closures near exempt calls are still reported', function (): void {
    $code = <<<'PHP'
        <?php
        test('a', fn (): bool => true)->with(fn (): array => []);
        array_map(fn (int $a): int => $a, []);
        $items->map(function (int $a): int {
            return $a;
        });
        $call(fn (): int => 1);
        new Foo(fn (): int => 1);
        $x = fn (): int => 1;
        PHP;

    expect(staticClosureLines($code))->toBe([2, 3, 4, 7, 8, 9]);
});

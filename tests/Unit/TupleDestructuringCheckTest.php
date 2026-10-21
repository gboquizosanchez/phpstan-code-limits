<?php

declare(strict_types=1);

use Boquizo\PHPStanCodeLimits\Checks\TupleDestructuringCheck;
use Boquizo\PHPStanCodeLimits\Checks\Violation;

/**
 * @return list<Violation>
 */
function tupleViolations(string $code): array
{
    return scan(new TupleDestructuringCheck, 'x.php', $code);
}

test('the rule id is tuple', function (): void {
    expect(new TupleDestructuringCheck()->id())->toBe('tuple');
});

test('destructuring an own method returning array is reported with a hint', function (): void {
    $code = <<<'PHP'
        <?php
        final class A
        {
            public function run(): void
            {
                [$a, $b] = $this->pair();
            }

            private function pair(): array
            {
                return [1, 2];
            }
        }
        PHP;

    $violations = tupleViolations($code);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->rule)->toBe('tuple')
        ->and($violations[0]->line)->toBe(6)
        ->and($violations[0]->message)->toBe('pair() returns a tuple; return a small readonly value object instead.');
});

test('list(), keyed destructuring, self:: and static:: calls are reported', function (): void {
    $code = <<<'PHP'
        <?php
        final class A
        {
            public function run(): void
            {
                list($a, $b) = $this->pair();
                ['x' => $x, 'y' => $y] = self::pair();
                [$c, $d] = static::untyped();
            }

            private function pair(): array
            {
                return [1, 2];
            }

            private function untyped()
            {
                return [1, 2];
            }
        }
        PHP;

    $lines = array_map(static fn (Violation $violation): int => $violation->line, tupleViolations($code));

    expect($lines)->toBe([6, 7, 8]);
});

test('producers with another return type are not tuples', function (): void {
    $code = <<<'PHP'
        <?php
        final class A
        {
            public function run(): void
            {
                [$a, $b] = $this->pair();
                [$c, $d] = $this->maybe();
            }

            private function pair(): Pair
            {
                return new Pair();
            }

            private function maybe(): ?array
            {
                return null;
            }
        }
        PHP;

    expect(tupleViolations($code))->toBe([]);
});

test('vendor functions, other receivers and unknown methods are not reported', function (): void {
    $code = <<<'PHP'
        <?php
        final class A
        {
            public function run(): void
            {
                [$a, $b] = explode(',', 'a,b');
                [$c, $d] = $this->other->pair();
                [$e, $f] = $other->pair();
                [$g, $h] = Other::pair();
                [$i, $j] = $this->missing();
                [$k, $l] = $this->{$name}();
                [$m, $n] = $value;
                [$o, $p] = [1, 2];
            }

            private function pair(): array
            {
                return [1, 2];
            }
        }
        PHP;

    expect(tupleViolations($code))->toBe([]);
});

test('a producer declared in another file is not seen', function (): void {
    $code = <<<'PHP'
        <?php
        final class A extends Base
        {
            public function run(): void
            {
                [$a, $b] = $this->pairFromParent();
            }
        }
        PHP;

    expect(tupleViolations($code))->toBe([]);
});

test('a plain assignment of an own array method is not destructuring', function (): void {
    $code = <<<'PHP'
        <?php
        final class A
        {
            public function run(): void
            {
                $pair = $this->pair();
            }

            private function pair(): array
            {
                return [1, 2];
            }
        }
        PHP;

    expect(tupleViolations($code))->toBe([]);
});

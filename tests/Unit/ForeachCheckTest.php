<?php

declare(strict_types=1);

use Boquizo\PHPStanCodeLimits\Checks\ForeachCheck;
use Boquizo\PHPStanCodeLimits\Checks\Violation;

/**
 * @return list<Violation>
 */
function foreachViolations(string $subject, string $assigned = '$item'): array
{
    $code = "<?php\nfinal class A\n{\n    public function run(): void\n    {\n"
        ."        foreach ({$subject} as {$assigned}) {\n        }\n    }\n}\n";

    return scan(new ForeachCheck, 'x.php', $code);
}

/**
 * A method-call subject padded so that the foreach header, written on one line
 * and including ` {`, has exactly $length characters.
 */
function foreachSubjectOfLength(int $length): string
{
    $base = mb_strlen('        foreach ($this->items() as $item) {');

    return '$this->items'.str_repeat('x', $length - $base).'()';
}

test('the rule id is foreach', function (): void {
    expect(new ForeachCheck()->id())->toBe('foreach');
});

test('variables and properties are fine, whatever the line length', function (string $subject): void {
    expect(foreachViolations($subject))->toBe([]);
})->with([
    'variable' => '$items',
    'property' => '$this->items',
    'nullsafe property' => '$order?->items',
    'static property' => 'self::$items',
    'class constant' => 'self::LEGACY_TO_CURRENT',
    'long property' => '$this->aVeryLongPropertyNameThatMakesTheHeaderLongerThanEightyCharactersForSure',
]);

test('one call without calls in its arguments is fine', function (string $subject): void {
    expect(foreachViolations($subject))->toBe([]);
})->with([
    'function' => 'array_keys($items)',
    'method' => '$this->getVouchers()',
    'nullsafe method' => '$order?->getVouchers()',
    'static method' => 'Voucher::all()',
    'chain' => '$this->query()->get()',
    'arguments without calls' => '$this->find($a, [1, 2], $b->c, $d ?? 1)',
]);

test('a call header of exactly 80 characters is fine and 81 is reported', function (): void {
    expect(foreachViolations(foreachSubjectOfLength(80)))->toBe([]);

    $violations = foreachViolations(foreachSubjectOfLength(81));

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->rule)->toBe('foreach')
        ->and($violations[0]->line)->toBe(6)
        ->and($violations[0]->message)->toBe(
            'the foreach header is 81 characters on one line, assign the collection to a variable first.',
        );
});

test('the header measure includes the key and by-reference values', function (): void {
    $subject = foreachSubjectOfLength(80);

    expect(foreachViolations($subject, '$k => $v'))->toHaveCount(1)
        ->and(foreachViolations($subject, '&$item'))->toHaveCount(1)
        ->and(foreachViolations('$this->items()', '$k => $v'))->toBe([]);
});

test('a header written on several lines is measured as one line', function (): void {
    $code = "<?php\nforeach (\n    \$this->getAVeryLongNamedCollectionOfThings(\$first, \$second)\n"
        ." as \$key => \$value\n) {\n}\n";

    expect(scan(new ForeachCheck, 'x.php', $code))->toHaveCount(1);
});

test('expressions are reported with a hint', function (string $subject): void {
    $violations = foreachViolations($subject);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->message)->toBe(
            'iterate over a variable, a property or a single call without calls in its arguments;'
            .' assign anything else to a named variable first.',
        );
})->with([
    'ternary' => '$a ? $b : $c',
    'short ternary' => '$a ?: $b',
    'null coalescing' => '$a ?? []',
    'operator' => '$a + $b',
    'array literal' => '[1, 2, 3]',
    'cast' => '(array) $a',
    'new' => 'new Foo()',
    'call in a call' => 'f(g($a))',
    'method call in a method call' => '$this->a($this->b())',
    'new in a call' => 'f(new Foo())',
    'static call in a call' => 'A::f(B::g())',
    'first-class callable' => 'strlen(...)',
]);

<?php

declare(strict_types=1);

use Boquizo\PHPStanCodeLimits\Checks\NestedArgumentsCheck;
use Boquizo\PHPStanCodeLimits\Checks\Violation;

/**
 * @return list<Violation>
 */
function nestedViolations(string $code): array
{
    return scan(new NestedArgumentsCheck, 'x.php', "<?php\n{$code}\n");
}

/**
 * A depth-2 statement at indentation 0 padded to exactly $length characters.
 */
function nestedStatementOfLength(int $length): string
{
    $base = mb_strlen('$x = outer(inner(deep()));');

    return '$x'.str_repeat('x', $length - $base).' = outer(inner(deep()));';
}

test('the rule id is nestedArgs', function (): void {
    expect(new NestedArgumentsCheck()->id())->toBe('nestedArgs');
});

test('one call inside another is fine', function (): void {
    $code = '$aVariableNameThatIsLongEnoughToPushEveryStatementBeyondEightyCharacters = outer(count($items), count($others), count($more));';

    expect(nestedViolations($code))->toBe([]);
});

test('two levels in a statement of exactly 80 characters are fine and 81 are reported', function (): void {
    expect(nestedViolations(nestedStatementOfLength(80)))->toBe([]);

    $violations = nestedViolations(nestedStatementOfLength(81));

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->rule)->toBe('nestedArgs')
        ->and($violations[0]->line)->toBe(2)
        ->and($violations[0]->message)->toBe(
            'two or more nested calls in the arguments, assign the inner call to a named variable first.',
        );
});

test('the indentation counts towards the 80 characters', function (): void {
    $short = nestedStatementOfLength(77);
    $code = "final class A\n{\n    public function run(): void\n    {\n        {$short}\n    }\n}";

    expect(nestedViolations($code))->toHaveCount(1);
});

test('a statement written on several lines is measured collapsed to one', function (): void {
    expect(nestedViolations("\$x = outer(\n    inner(deep()),\n);"))->toBe([]);
});

test('three levels are reported once, at the outermost call', function (): void {
    $code = '$longVariableName = outerFunction(innerFunction(deeperFunction(deepestFunction($argument))));';

    $violations = nestedViolations($code);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->line)->toBe(2);
});

test('the kinds of call all count as levels', function (string $expression): void {
    expect(nestedViolations("\$aVariableNameThatIsLongEnoughToPushEveryStatementBeyondEightyCharacters = {$expression};"))->toHaveCount(1);
})->with([
    'functions' => 'outer(inner(deep($a)))',
    'methods' => '$this->outer($this->inner($this->deep($a)))',
    'nullsafe methods' => '$this?->outer($this?->inner($this?->deep($a)))',
    'static methods' => 'A::outer(B::inner(C::deep($a)))',
    'new' => 'new Outer(new Inner(new Deep($a)))',
    'new inside a call' => 'outer(new Inner(deep($a)))',
    'calls in an array argument' => 'outer([inner(deep($a))])',
    'calls in a ternary argument' => 'outer($a ? inner(deep($a)) : 1)',
    'calls in the receiver of an inner call' => 'outer($this->inner(deep($a))->more())',
]);

test('sprintf, __ and trans arguments do not count as a level', function (): void {
    $long = '$aVariableNameThatIsLongEnoughToPushEveryStatementBeyondEightyCharacters = outer(%s);';

    expect(nestedViolations(sprintf($long, "sprintf('%s', inner(\$a))")))->toBe([])
        ->and(nestedViolations(sprintf($long, "__('key', ['a' => inner(\$a)])")))->toBe([])
        ->and(nestedViolations(sprintf($long, "trans('key', ['a' => inner(\$a)])")))->toBe([])
        ->and(nestedViolations(sprintf($long, "\\sprintf('%s', inner(\$a))")))->toBe([]);
});

test('sprintf does not hide a deeper nesting', function (): void {
    $long = '$aVariableNameThatIsLongEnoughToPushEveryStatementBeyondEightyCharacters = outer(%s);';

    expect(nestedViolations(sprintf($long, "sprintf('%s', inner(deep(\$a)))")))->toHaveCount(1)
        ->and(nestedViolations('$aVariableNameThatIsLongEnoughToPushEveryStatementBeyondEightyCharacters = sprintf(inner(deep($a)));'))
        ->toHaveCount(1);
});

test('a chain of calls is not nesting', function (): void {
    $code = '$aVariableNameThatIsLongEnoughToPushEveryStatementBeyondEightyCharacters = $this->a(b())->c(d())->e(f($g)->h());';

    expect(nestedViolations($code))->toBe([]);
});

test('a nested chain in the argument counts its deepest arguments only', function (): void {
    $code = '$aVariableNameThatIsLongEnoughToPushEveryStatementBeyondEightyCharacters = outer($this->a()->b()->c());';

    expect(nestedViolations($code))->toBe([]);
});

test('closures and arrow functions in the arguments are not entered', function (): void {
    $code = <<<'PHP'
        $aVariableNameThatIsLongEnoughToPushEveryStatementBeyondEightyCharacters = outer(static function (): int {
            return 1;
        }, static fn (): int => inner($a), static fn (): int => 1);
        PHP;

    expect(nestedViolations($code))->toBe([]);
});

test('the body of a closure is judged on its own statements', function (): void {
    $code = <<<'PHP'
        $f = static function (): int {
            return outerFunctionName(innerFunctionName(deepFunctionName($argumentNumberOne)));
        };
        $g = static fn (): int => outerFunctionName(innerFunctionName(deepFunctionName($a)));
        PHP;

    $lines = array_map(static fn (Violation $violation): int => $violation->line, nestedViolations($code));

    expect($lines)->toBe([3, 5]);
});

test('first-class callables are not calls', function (): void {
    $code = '$aVariableNameThatIsLongEnoughToPushEveryStatementBeyondEightyCharacters = outerFunction(innerFunction(strlen(...)));';

    expect(nestedViolations($code))->toBe([])
        ->and(nestedViolations('$x = outer(inner(deep(...)), 1)'.str_repeat(' ', 60).';'))->toBe([]);
});

test('a compound statement is measured whole', function (): void {
    $code = <<<'PHP'
        if (a(b(c()))) {
            $aVariableNameThatIsLongEnoughToPushTheWholeStatementBeyondEightyCharacters = 1;
        }
        PHP;

    expect(nestedViolations($code))->toHaveCount(1)
        ->and(nestedViolations('if (a(b(c()))) {}'))->toBe([]);
});

test('calls without arguments and plain code are clean', function (): void {
    $code = <<<'PHP'
        $x = a();
        $y = a(1, $b, [2], new B());
        $z = $a->b()->c();
        PHP;

    expect(nestedViolations($code))->toBe([]);
});

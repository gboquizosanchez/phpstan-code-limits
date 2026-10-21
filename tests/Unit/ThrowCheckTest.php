<?php

declare(strict_types=1);

use Boquizo\PHPStanCodeLimits\Checks\ThrowCheck;
use Boquizo\PHPStanCodeLimits\Checks\Violation;

/**
 * @return list<Violation>
 */
function throwViolations(string $statement): array
{
    $code = "<?php\nfinal class A\n{\n    public function run(): void\n    {\n{$statement}\n    }\n}\n";

    return scan(new ThrowCheck, 'x.php', $code);
}

/**
 * The padded message so that the call written on one line, indented by 8
 * spaces and with its `;`, has exactly $length characters.
 */
function throwMessage(int $length): string
{
    $base = mb_strlen("        throw_if(\$a, RuntimeException::class, '');");

    return "'".str_repeat('x', $length - $base)."'";
}

function throwOneLine(int $length): string
{
    return '        throw_if($a > 1, RuntimeException::class, '.throwMessage($length - 4).');';
}

function throwWrapped(int $length, string $closing = ',', string $first = "\n"): string
{
    return "        throw_if({$first}            \$a > 1,\n            RuntimeException::class,\n"
        .'            '.throwMessage($length - 4)."{$closing}\n        );";
}

test('the rule id is throw', function (): void {
    expect(new ThrowCheck()->id())->toBe('throw');
});

test('a statement of exactly 80 characters on one line is fine', function (): void {
    expect(throwViolations(throwOneLine(80)))->toBe([]);
});

test('a statement of 80 characters or less written on several lines is reported', function (): void {
    $violations = throwViolations(throwWrapped(80));

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->rule)->toBe('throw')
        ->and($violations[0]->line)->toBe(6)
        ->and($violations[0]->message)->toBe('throw_if(): the statement fits in 80 characters, write it on one line.');
});

test('a statement of 81 characters on one line is reported', function (): void {
    $violations = throwViolations(throwOneLine(81));

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->message)->toBe(
            'throw_if(): the statement is 81 characters on one line,'
            .' write one argument per line with a trailing comma.',
        );
});

test('a statement of 81 characters with one argument per line and a trailing comma is fine', function (): void {
    expect(throwViolations(throwWrapped(81)))->toBe([]);
});

test('the layout of a long statement must be one argument per line', function (string $statement): void {
    expect(throwViolations($statement))->toHaveCount(1);
})->with([
    'no trailing comma' => fn (): string => throwWrapped(81, ''),
    'first argument on the call line' => fn (): string => throwWrapped(81, ',', ''),
    'two arguments on a line' => fn (): string => "        throw_if(\n            \$a > 1, RuntimeException::class,\n"
        .'            '.throwMessage(77).",\n        );",
    'closing parenthesis after the last argument' => fn (): string => "        throw_if(\n            \$a > 1,\n"
        ."            RuntimeException::class,\n            ".throwMessage(77).',);',
]);

test('throw_unless, a leading backslash and any case are covered', function (): void {
    $statements = [
        '        throw_unless($a > 1, RuntimeException::class, '.throwMessage(80).');',
        '        \throw_if($a > 1, RuntimeException::class, '.throwMessage(80).');',
        '        THROW_IF($a > 1, RuntimeException::class, '.throwMessage(80).');',
    ];

    foreach ($statements as $statement) {
        expect(throwViolations($statement))->toHaveCount(1);
    }
});

test('methods and other functions named alike are not covered', function (): void {
    $message = throwMessage(90);

    expect(throwViolations("        \$this->throw_if(\$a, X::class, {$message});"))->toBe([])
        ->and(throwViolations("        throw_if_not(\$a, X::class, {$message});"))->toBe([])
        ->and(throwViolations('        throw_if(...);'))->toBe([])
        ->and(throwViolations('        throw_if();'))->toBe([]);
});

test('a variable condition is reported', function (): void {
    $violations = throwViolations('        throw_if($isMissing, X::class, \'Missing.\');');

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->message)->toBe('throw_if(): the condition must be an inline expression, not a variable.');
});

test('a variable message is reported', function (): void {
    $violations = throwViolations('        throw_unless($a > 1, X::class, $message);');

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->message)->toBe(
            'throw_unless(): the message must be an inline expression, not a variable.',
        );
});

test('a variable exception and calls as condition or message are fine', function (): void {
    expect(throwViolations('        throw_if($a > 1, $exception);'))->toBe([])
        ->and(throwViolations('        throw_if($a->isMissing(), X::class, sprintf(\'%s\', $b));'))->toBe([]);
});

test('layout and argument problems are reported separately', function (): void {
    $statement = '        throw_if($isMissing, RuntimeException::class, '.throwMessage(90).');';

    $violations = throwViolations($statement);

    expect($violations)->toHaveCount(2)
        ->and($violations[0]->message)->toContain('characters on one line')
        ->and($violations[1]->message)->toContain('the condition');
});

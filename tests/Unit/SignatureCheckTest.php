<?php

declare(strict_types=1);

use Boquizo\PHPStanCodeLimits\Checks\SignatureCheck;
use Boquizo\PHPStanCodeLimits\Checks\Violation;

/**
 * @return list<Violation>
 */
function signatureViolations(string $methods): array
{
    $code = "<?php\nfinal class A\n{\n{$methods}\n}\n";

    return scan(new SignatureCheck, 'x.php', $code);
}

/**
 * Padding for the second parameter name so that the signature written on one
 * line, up to and including the opening brace, has exactly $length characters.
 */
function signaturePadding(int $length): string
{
    return str_repeat('x', $length - mb_strlen('    public function run(int $a, int $b): void {'));
}

function signatureOneLine(int $length): string
{
    $padding = signaturePadding($length);

    return "    public function run(int \$a, int \$b{$padding}): void {}";
}

/**
 * @param  list<string>  $layout  the lines that follow the name line, with {b} for the padded name
 */
function signatureWrapped(int $length, array $layout): string
{
    $body = implode("\n", $layout);

    return str_replace('{b}', '$b'.signaturePadding($length), $body);
}

test('the rule id is signature', function (): void {
    expect(new SignatureCheck()->id())->toBe('signature');
});

test('exactly 80 characters on one line is fine', function (): void {
    expect(signatureViolations(signatureOneLine(80)))->toBe([]);
});

test('81 characters on one line is reported', function (): void {
    $violations = signatureViolations(signatureOneLine(81));

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->rule)->toBe('signature')
        ->and($violations[0]->line)->toBe(4)
        ->and($violations[0]->message)->toBe(
            'run(): signature is 81 characters on one line, write one parameter per line with a trailing comma.',
        );
});

test('a one per line signature is fine at 81 and reported at 80', function (): void {
    $layout = [
        '    public function run(',
        '        int $a,',
        '        int {b},',
        '    ): void {}',
    ];

    $long = signatureViolations(signatureWrapped(81, $layout));
    $short = signatureViolations(signatureWrapped(80, $layout));

    expect($long)->toBe([])
        ->and($short)->toHaveCount(1)
        ->and($short[0]->message)->toBe('run(): signature fits in 80 characters, write it on one line.');
});

test('a long signature with a bad wrapped layout is reported', function (string $name, array $layout): void {
    $violations = signatureViolations(signatureWrapped(81, $layout));

    expect($violations)->toHaveCount(1);
})->with([
    'no trailing comma' => [
        'no trailing comma',
        [
            '    public function run(',
            '        int $a,',
            '        int {b}',
            '    ): void {}',
        ],
    ],
    'two parameters on a line' => [
        'two parameters on a line',
        [
            '    public function run(',
            '        int $a, int {b},',
            '    ): void {}',
        ],
    ],
    'first parameter on the name line' => [
        'first parameter on the name line',
        [
            '    public function run(int $a,',
            '        int {b},',
            '    ): void {}',
        ],
    ],
    'closing parenthesis on the last parameter line' => [
        'closing parenthesis on the last parameter line',
        [
            '    public function run(',
            '        int $a,',
            '        int {b},): void {}',
        ],
    ],
]);

test('a short signature wrapped over several lines is reported', function (): void {
    $violations = signatureViolations(implode("\n", [
        '    public function run(',
        '        int $a,',
        '    ): void {}',
    ]));

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->message)->toContain('write it on one line');
});

test('a multiline default value on the name line makes the signature wrapped', function (): void {
    $violations = signatureViolations(implode("\n", [
        '    public function run(array $a = [',
        '        1,',
        '    ]): void {}',
    ]));

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->message)->toBe('run(): signature fits in 50 characters, write it on one line.');
});

test('a brace on its own line is measured as appended to the signature', function (): void {
    $padding = signaturePadding(80);
    $ok = "    public function run(int \$a, int \$b{$padding}): void\n    {\n    }";
    $long = "    public function run(int \$a, int \$b{$padding}x): void\n    {\n    }";
    $wrapped = "    public function run(\n        int \$a,\n        int \$b{$padding}x,\n    ): void\n    {\n    }";

    expect(signatureViolations($ok))->toBe([])
        ->and(signatureViolations($long))->toHaveCount(1)
        ->and(signatureViolations($wrapped))->toBe([]);
});

test('abstract and interface methods end at the semicolon', function (): void {
    $code = <<<'PHP'
<?php
interface I
{
    public function short(int $a): int;
    public function long(
        int $aaaaaaaaaaaaaaaaaaaaaaaa,
        int $bbbbbbbbbbbbbbbbbbbbbbbbbbbbb,
        int $cccccccccccccccccccccccccccc,
    ): int;
}
PHP;

    expect(scan(new SignatureCheck, 'x.php', $code))->toBe([]);
});

test('plain functions, no parameters and promoted constructors are skipped', function (): void {
    $code = <<<'PHP'
<?php
function none(): void
{
}
final class A
{
    public function __construct(public int $a) {}
}
function ok(int $a): int { return $a; }
PHP;

    expect(scan(new SignatureCheck, 'x.php', $code))->toBe([]);
});

test('a plain constructor and a plain function are measured', function (): void {
    $code = <<<'PHP'
<?php
function wrapped(
    int $a,
): void {}
final class A
{
    public function __construct(int $a) {}
}
PHP;

    $violations = scan(new SignatureCheck, 'x.php', $code);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->line)->toBe(2);
});

test('multibyte characters are positioned by bytes and measured by characters', function (): void {
    $code = <<<'PHP'
<?php
final class A
{
    // ñññ ñññ
    public function run(string $a = 'ñññ', int $b = 1): void {}
}
PHP;

    expect(scan(new SignatureCheck, 'x.php', $code))->toBe([]);
});

test('a signature on the first line of the file is measured', function (): void {
    $violations = scan(new SignatureCheck, 'x.php', '<?php function f(int $a) {}');

    expect($violations)->toBe([]);
});

test('the declaration line is the one holding the name', function (): void {
    $code = <<<'PHP'
<?php
#[Attr]
function f(
    int $a,
): void {}
PHP;

    $violations = scan(new SignatureCheck, 'x.php', $code);

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->line)->toBe(3);
});

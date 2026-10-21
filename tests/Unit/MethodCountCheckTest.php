<?php

declare(strict_types=1);

use Boquizo\PHPStanCodeLimits\Checks\MethodCountCheck;

function methodsSource(string $keyword, int $count): string
{
    $methods = '';

    for ($i = 1; $i <= $count; $i++) {
        $methods .= $keyword === 'interface'
            ? "    public function m{$i}(): void;\n"
            : "    public function m{$i}(): void {}\n";
    }

    return "<?php\n{$keyword} Sample\n{\n{$methods}}\n";
}

test('the rule id is methods', function (): void {
    expect(new MethodCountCheck()->id())->toBe('methods');
});

test('twenty methods are allowed', function (string $keyword): void {
    $violations = scan(new MethodCountCheck, 'x.php', methodsSource($keyword, 20));

    expect($violations)->toBe([]);
})->with(['class', 'trait', 'interface', 'enum']);

test('twenty one methods are reported', function (string $keyword): void {
    $violations = scan(new MethodCountCheck, 'x.php', methodsSource($keyword, 21));

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->line)->toBe(2)
        ->and($violations[0]->message)->toBe('Sample has 21 methods, the limit is 20.');
})->with(['class', 'trait', 'interface', 'enum']);

test('an anonymous class is reported without a name', function (): void {
    $methods = '';

    for ($i = 1; $i <= 21; $i++) {
        $methods .= "public function m{$i}(): void {}\n";
    }

    $violations = scan(new MethodCountCheck, 'x.php', "<?php\n\$a = new class {\n{$methods}};\n");

    expect($violations)->toHaveCount(1)
        ->and($violations[0]->message)->toBe('Anonymous class has 21 methods, the limit is 20.');
});

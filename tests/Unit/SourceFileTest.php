<?php

declare(strict_types=1);

use Boquizo\PHPStanCodeLimits\Checks\SourceFile;
use PhpParser\Node\Stmt\Expression;

test('parse keeps the path, the code, the lines and the ast', function (): void {
    $file = SourceFile::parse('a.php', "<?php\n\$a = 1;\n");

    expect($file->path)->toBe('a.php')
        ->and($file->code)->toBe("<?php\n\$a = 1;\n")
        ->and($file->lines)->toBe(['<?php', '$a = 1;', ''])
        ->and($file->ast)->toHaveCount(1)
        ->and($file->ast[0])->toBeInstanceOf(Expression::class);
});

test('parse throws on a syntax error', function (): void {
    SourceFile::parse('a.php', '<?php $a = ;');
})->throws(InvalidArgumentException::class);

test('an empty file has an empty ast', function (): void {
    expect(SourceFile::parse('a.php', '')->ast)->toBe([]);
});

test('slice works on bytes and lineStart returns byte offsets', function (): void {
    $file = SourceFile::parse('a.php', "<?php\n// ñ\n\$a = 1;\n");

    expect($file->slice(0, 5))->toBe('<?php')
        ->and($file->lineStart(1))->toBe(0)
        ->and($file->lineStart(2))->toBe(6)
        ->and($file->lineStart(3))->toBe(12);
});

test('collapse joins wrapped lines with a single space', function (): void {
    expect(SourceFile::collapse("[\n    1,\n    2,\n]"))->toBe('[ 1, 2, ]')
        ->and(SourceFile::collapse('a  b'))->toBe('a  b');
});

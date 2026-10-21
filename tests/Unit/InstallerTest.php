<?php

declare(strict_types=1);

use Boquizo\PHPStanCodeLimits\Installer;

function installerProject(string $neon, bool $extensionInstaller): string
{
    $dir = sprintf('%s/code-limits-%s', sys_get_temp_dir(), bin2hex(random_bytes(6)));
    mkdir($dir);
    file_put_contents("{$dir}/phpstan.neon", $neon);

    if ($extensionInstaller) {
        mkdir("{$dir}/vendor/phpstan/extension-installer", recursive: true);
    }

    return $dir;
}

test('with extension-installer only the complexity file is added', function (): void {
    $dir = installerProject("includes:\n    - a.neon\n\nparameters:\n    level: max\n", true);

    $added = new Installer($dir)->install();

    expect($added)->toBe(['vendor/gboquizosanchez/phpstan-code-limits/complexity.neon'])
        ->and(file_get_contents("{$dir}/phpstan.neon"))->toBe(
            "includes:\n"
            ."    - vendor/gboquizosanchez/phpstan-code-limits/complexity.neon\n"
            ."    - a.neon\n\nparameters:\n    level: max\n",
        );
});

test('without extension-installer the extensions are added before the complexity file', function (): void {
    $dir = installerProject("parameters:\n    level: max\n", false);

    new Installer($dir)->install();

    expect(file_get_contents("{$dir}/phpstan.neon"))->toBe(
        "includes:\n"
        ."    - vendor/tomasvotruba/cognitive-complexity/config/extension.neon\n"
        ."    - vendor/gboquizosanchez/phpstan-code-limits/extension.neon\n"
        ."    - vendor/gboquizosanchez/phpstan-code-limits/complexity.neon\n"
        ."\nparameters:\n    level: max\n",
    );
});

test('running it twice adds nothing the second time', function (): void {
    $dir = installerProject("parameters:\n    level: max\n", false);
    new Installer($dir)->install();
    $before = file_get_contents("{$dir}/phpstan.neon");

    expect(new Installer($dir)->install())->toBe([])
        ->and(file_get_contents("{$dir}/phpstan.neon"))->toBe($before);
});

test('a missing phpstan.neon is an error', function (): void {
    new Installer(sys_get_temp_dir().'/code-limits-missing')->install();
})->throws(RuntimeException::class);

<?php

declare(strict_types=1);

use Boquizo\PHPStanCodeLimits\Installer;

function installerProject(string $neon, bool $extensionInstaller, bool $laravel = false): string
{
    $dir = sprintf('%s/code-limits-%s', sys_get_temp_dir(), bin2hex(random_bytes(6)));
    mkdir($dir);
    file_put_contents("{$dir}/phpstan.neon", $neon);

    if ($extensionInstaller) {
        mkdir("{$dir}/vendor/phpstan/extension-installer", recursive: true);
    }

    if ($laravel) {
        touch("{$dir}/artisan");
    }

    return $dir;
}

test('the entry point depends on extension-installer and Laravel', function (bool $installer, bool $laravel, string $entry): void {
    $dir = installerProject("parameters:\n    level: max\n", $installer, $laravel);

    expect(new Installer($dir)->install())->toBe("vendor/gboquizosanchez/phpstan-code-limits/{$entry}")
        ->and(file_get_contents("{$dir}/phpstan.neon"))->toBe(
            "includes:\n    - vendor/gboquizosanchez/phpstan-code-limits/{$entry}\n\nparameters:\n    level: max\n",
        );
})->with([
    'installer' => [true, false, 'complexity.neon'],
    'installer and Laravel' => [true, true, 'laravel.neon'],
    'no installer' => [false, false, 'standalone.neon'],
    'no installer and Laravel' => [false, true, 'standalone-laravel.neon'],
]);

test('the line goes first in an existing includes section', function (): void {
    $dir = installerProject("includes:\n    - a.neon\n\nparameters:\n    level: max\n", true);

    new Installer($dir)->install();

    expect(file_get_contents("{$dir}/phpstan.neon"))->toBe(
        "includes:\n"
        ."    - vendor/gboquizosanchez/phpstan-code-limits/complexity.neon\n"
        ."    - a.neon\n\nparameters:\n    level: max\n",
    );
});

test('the lines of older versions are replaced by the single entry point', function (): void {
    $neon = "includes:\n"
        ."    - vendor/tomasvotruba/cognitive-complexity/config/extension.neon\n"
        ."    - vendor/gboquizosanchez/phpstan-code-limits/extension.neon\n"
        ."    - vendor/gboquizosanchez/phpstan-code-limits/complexity.neon\n"
        ."    - vendor/larastan/larastan/extension.neon\n";
    $dir = installerProject($neon, false, true);

    new Installer($dir)->install();

    expect(file_get_contents("{$dir}/phpstan.neon"))->toBe(
        "includes:\n"
        ."    - vendor/gboquizosanchez/phpstan-code-limits/standalone-laravel.neon\n"
        ."    - vendor/larastan/larastan/extension.neon\n",
    );
});

test('running it twice changes nothing the second time', function (): void {
    $dir = installerProject("parameters:\n    level: max\n", false);
    new Installer($dir)->install();
    $before = file_get_contents("{$dir}/phpstan.neon");

    expect(new Installer($dir)->install())->toBeNull()
        ->and(file_get_contents("{$dir}/phpstan.neon"))->toBe($before);
});

test('a missing phpstan.neon is an error', function (): void {
    new Installer(sys_get_temp_dir().'/code-limits-missing')->install();
})->throws(RuntimeException::class);

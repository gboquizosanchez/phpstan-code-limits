<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits;

use RuntimeException;

/**
 * Writes the single include line a project's phpstan.neon needs from this
 * package, replacing the lines older versions wrote. Running it twice changes
 * nothing.
 *
 * The entry point depends on the project: with phpstan/extension-installer the
 * extensions are already loaded (including them again fails), and a Laravel
 * project (one with an `artisan` file) also gets the Laravel ignores.
 */
final readonly class Installer
{
    private const string VENDOR = 'vendor/gboquizosanchez/phpstan-code-limits';

    private const string PACKAGE_LINES = '~^[ \t]*-[ \t]*vendor/(?:tomasvotruba/cognitive-complexity/config/extension|gboquizosanchez/phpstan-code-limits/[\w-]+)\.neon[ \t]*\n~m';

    public function __construct(
        private string $projectPath,
    ) {}

    /**
     * @return string|null the include line written, null when already there
     */
    public function install(): ?string
    {
        $config = "{$this->projectPath}/phpstan.neon";
        $neon = $this->read($config);
        $entry = sprintf('%s/%s', self::VENDOR, $this->entryFile());
        $cleaned = preg_replace(self::PACKAGE_LINES, '', $neon) ?? $neon;
        $updated = $this->withInclude($cleaned, $entry);

        if ($updated === $neon) {
            return null;
        }

        file_put_contents($config, $updated);

        return $entry;
    }

    private function entryFile(): string
    {
        $installer = "{$this->projectPath}/vendor/phpstan/extension-installer";
        $standalone = ! is_dir($installer);
        $laravel = is_file("{$this->projectPath}/artisan");

        return match (true) {
            $standalone && $laravel => 'standalone-laravel.neon',
            $standalone => 'standalone.neon',
            $laravel => 'laravel.neon',
            default => 'complexity.neon',
        };
    }

    private function read(string $config): string
    {
        $neon = false;

        if (is_file($config)) {
            $neon = file_get_contents($config);
        }

        if ($neon === false) {
            throw new RuntimeException("Cannot read {$config}.");
        }

        return $neon;
    }

    private function withInclude(string $neon, string $include): string
    {
        $line = "    - {$include}\n";
        $section = preg_match('/^includes:[ \t]*\n/m', $neon, $match, PREG_OFFSET_CAPTURE);

        if ($section !== 1) {
            return "includes:\n{$line}\n{$neon}";
        }

        $end = $match[0][1] + mb_strlen($match[0][0], '8bit');

        return substr_replace($neon, $line, $end, 0);
    }
}

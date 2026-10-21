<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits;

use RuntimeException;

/**
 * Adds the package's configuration files to the `includes` of a project's
 * phpstan.neon. Files already included are left alone, so it can run twice.
 *
 * Without phpstan/extension-installer the extensions themselves are added too.
 */
final readonly class Installer
{
    private const string VENDOR = 'vendor/gboquizosanchez/phpstan-code-limits';

    public function __construct(
        private string $projectPath,
    ) {}

    /**
     * @return list<string> the include lines added
     */
    public function install(): array
    {
        $config = "{$this->projectPath}/phpstan.neon";
        $neon = $this->read($config);
        $missing = array_filter(
            $this->includes(),
            static fn (string $include): bool => ! str_contains($neon, $include),
        );
        $missing = array_values($missing);

        if ($missing !== []) {
            file_put_contents($config, $this->withIncludes($neon, $missing));
        }

        return $missing;
    }

    /**
     * @return list<string>
     */
    private function includes(): array
    {
        $includes = [];
        $installer = "{$this->projectPath}/vendor/phpstan/extension-installer";

        if (! is_dir($installer)) {
            $includes[] = 'vendor/tomasvotruba/cognitive-complexity/config/extension.neon';
            $includes[] = sprintf('%s/extension.neon', self::VENDOR);
        }

        $includes[] = sprintf('%s/complexity.neon', self::VENDOR);

        return $includes;
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

    /**
     * @param  list<string>  $includes
     */
    private function withIncludes(string $neon, array $includes): string
    {
        $lines = implode('', array_map(
            static fn (string $include): string => "    - {$include}\n",
            $includes,
        ));
        $section = preg_match('/^includes:[ \t]*\n/m', $neon, $match, PREG_OFFSET_CAPTURE);

        if ($section !== 1) {
            return "includes:\n{$lines}\n{$neon}";
        }

        $end = $match[0][1] + mb_strlen($match[0][0], '8bit');

        return substr_replace($neon, $lines, $end, 0);
    }
}

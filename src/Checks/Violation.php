<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

final readonly class Violation
{
    public function __construct(
        public string $rule,
        public string $file,
        public int $line,
        public string $message,
    ) {}
}

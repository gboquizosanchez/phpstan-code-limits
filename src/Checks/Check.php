<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

interface Check
{
    public function id(): string;

    /**
     * @return list<Violation>
     */
    public function check(SourceFile $file): array;
}

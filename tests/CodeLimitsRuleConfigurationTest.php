<?php

declare(strict_types=1);

namespace Tests;

use Boquizo\PHPStanCodeLimits\CodeLimitsRule;
use Override;
use PHPStan\Analyser\Error;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<CodeLimitsRule>
 */
final class CodeLimitsRuleConfigurationTest extends RuleTestCase
{
    #[Override]
    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(CodeLimitsRule::class);
    }

    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__.'/../extension.neon',
            __DIR__.'/Fixtures/limits.neon',
        ];
    }

    public function test_limits_come_from_the_configuration(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__.'/Fixtures/Violations.php']);
        $messages = array_map(
            static fn (Error $error): string => $error->getMessage(),
            $errors,
        );

        $this->assertContains('Violations has 6 methods, the limit is 2.', $messages);
        $this->assertNotContains('params() has 4 parameters, the limit is 3.', $messages);
    }
}

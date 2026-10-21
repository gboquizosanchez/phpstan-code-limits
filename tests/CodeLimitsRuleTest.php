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
final class CodeLimitsRuleTest extends RuleTestCase
{
    private const string FIXTURE = __DIR__.'/Fixtures/Violations.php';

    #[Override]
    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(CodeLimitsRule::class);
    }

    #[Override]
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__.'/../extension.neon'];
    }

    public function test_it_reports_every_check(): void
    {
        $this->analyse([self::FIXTURE], [
            ['constructor with promoted properties must have one parameter per line with a trailing comma.', 9],
            ['params() has 4 parameters, the limit is 3.', 11],
            ['returns() has 4 return statements, the limit is 3.', 13],
            ['signature(): signature is 96 characters on one line, write one parameter per line with a trailing comma.', 30],
            ['make it static unless it uses $this.', 34],
            ['iterate over a variable, a property or a single call without calls in its arguments; assign anything else to a named variable first.', 38],
            ['use string interpolation or sprintf() instead of concatenation.', 39],
            ['throw_if(): the statement is 85 characters on one line, write one argument per line with a trailing comma.', 42],
            ['write each condition of the arm on its own line.', 45],
            ['pair() returns a tuple; return a small readonly value object instead.', 49],
            ['list the use() variables in the order of their first usage: $c, $d.', 52],
            ['two or more nested calls in the arguments, assign the inner call to a named variable first.', 55],
            ['use string interpolation or sprintf() instead of concatenation.', 57],
            ['use three ASCII dots "..." instead of the ellipsis character.', 57],
        ]);
    }

    public function test_identifiers_are_prefixed_with_code_limits(): void
    {
        $errors = $this->gatherAnalyserErrors([self::FIXTURE]);
        $identifiers = array_map(
            static fn (Error $error): ?string => $error->getIdentifier(),
            $errors,
        );
        sort($identifiers);

        $this->assertSame([
            'codeLimits.closureStatic',
            'codeLimits.closureUseOrder',
            'codeLimits.concat',
            'codeLimits.concat',
            'codeLimits.ellipsis',
            'codeLimits.foreach',
            'codeLimits.matchArm',
            'codeLimits.nestedArgs',
            'codeLimits.params',
            'codeLimits.promotedCtor',
            'codeLimits.returns',
            'codeLimits.signature',
            'codeLimits.throw',
            'codeLimits.tuple',
        ], $identifiers);
    }
}

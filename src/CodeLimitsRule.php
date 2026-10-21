<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits;

use Boquizo\PHPStanCodeLimits\Checks\Check;
use Boquizo\PHPStanCodeLimits\Checks\ClosureUseOrderCheck;
use Boquizo\PHPStanCodeLimits\Checks\ConcatenationCheck;
use Boquizo\PHPStanCodeLimits\Checks\EllipsisCheck;
use Boquizo\PHPStanCodeLimits\Checks\ForeachCheck;
use Boquizo\PHPStanCodeLimits\Checks\MatchArmCheck;
use Boquizo\PHPStanCodeLimits\Checks\MethodCountCheck;
use Boquizo\PHPStanCodeLimits\Checks\NestedArgumentsCheck;
use Boquizo\PHPStanCodeLimits\Checks\ParameterCountCheck;
use Boquizo\PHPStanCodeLimits\Checks\PromotedConstructorCheck;
use Boquizo\PHPStanCodeLimits\Checks\ReturnCountCheck;
use Boquizo\PHPStanCodeLimits\Checks\SignatureCheck;
use Boquizo\PHPStanCodeLimits\Checks\SourceFile;
use Boquizo\PHPStanCodeLimits\Checks\StaticClosureCheck;
use Boquizo\PHPStanCodeLimits\Checks\ThrowCheck;
use Boquizo\PHPStanCodeLimits\Checks\TupleDestructuringCheck;
use InvalidArgumentException;
use Override;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Runs every check once per analyzed file and reports each violation with
 * the identifier `codeLimits.<check id>`.
 *
 * The file is parsed again because the checks need the source text and the
 * byte offsets of the nodes.
 *
 * @implements Rule<FileNode>
 */
final readonly class CodeLimitsRule implements Rule
{
    /**
     * @var list<Check>
     */
    private array $checks;

    /**
     * @param array{
     *     maxParameters: int,
     *     maxReturns: int,
     *     maxMethods: int,
     *     maxLineLength: int,
     * } $limits
     */
    public function __construct(array $limits)
    {
        $this->checks = [
            new ParameterCountCheck($limits['maxParameters']),
            new ReturnCountCheck($limits['maxReturns']),
            new MethodCountCheck($limits['maxMethods']),
            new SignatureCheck($limits['maxLineLength']),
            new PromotedConstructorCheck,
            new ConcatenationCheck,
            new EllipsisCheck,
            new StaticClosureCheck,
            new ClosureUseOrderCheck,
            new ThrowCheck($limits['maxLineLength']),
            new MatchArmCheck,
            new ForeachCheck($limits['maxLineLength']),
            new NestedArgumentsCheck($limits['maxLineLength']),
            new TupleDestructuringCheck,
        ];
    }

    #[Override]
    public function getNodeType(): string
    {
        return FileNode::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    #[Override]
    public function processNode(Node $node, Scope $scope): array
    {
        $path = $scope->getFile();
        $code = (string) file_get_contents($path);

        try {
            $file = SourceFile::parse($path, $code);
        } catch (InvalidArgumentException) {
            // ponytail: PHPStan already reports the parse error itself.
            return [];
        }

        $errors = [];

        foreach ($this->checks as $check) {
            foreach ($check->check($file) as $violation) {
                $errors[] = RuleErrorBuilder::message($violation->message)
                    ->identifier("codeLimits.{$violation->rule}")
                    ->line(max(1, $violation->line))
                    ->build();
            }
        }

        return $errors;
    }
}

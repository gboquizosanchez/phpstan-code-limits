<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;

final readonly class FunctionDeclaration
{
    public function __construct(
        public Function_|ClassMethod $node,
        public bool $inReadonlyClass,
    ) {}

    public function name(): string
    {
        return $this->node->name->toString();
    }

    public function line(): int
    {
        return $this->node->name->getStartLine();
    }

    public function isConstructor(): bool
    {
        return $this->node instanceof ClassMethod
            && $this->node->name->toLowerString() === '__construct';
    }

    public function hasPromotedParameter(): bool
    {
        return array_any(
            $this->node->params,
            static fn (Param $param): bool => $param->isPromoted(),
        );
    }
}

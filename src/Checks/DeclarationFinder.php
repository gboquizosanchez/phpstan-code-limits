<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;

/**
 * Lists the named functions and the methods of every class-like in a file.
 * Closures and arrow functions are deliberately not listed.
 */
final class DeclarationFinder
{
    /**
     * @return list<FunctionDeclaration>
     */
    public static function find(SourceFile $file): array
    {
        $finder = new NodeFinder;
        $declarations = [];

        $functions = $finder->findInstanceOf($file->ast, Function_::class);
        $classes = $finder->findInstanceOf($file->ast, ClassLike::class);

        foreach ($functions as $function) {
            $declarations[] = new FunctionDeclaration($function, false);
        }

        foreach ($classes as $class) {
            $readonly = $class instanceof Class_ && $class->isReadonly();

            foreach ($class->getMethods() as $method) {
                $declarations[] = new FunctionDeclaration($method, $readonly);
            }
        }

        return $declarations;
    }
}

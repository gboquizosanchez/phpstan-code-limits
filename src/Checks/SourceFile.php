<?php

declare(strict_types=1);

namespace Boquizo\PHPStanCodeLimits\Checks;

use InvalidArgumentException;
use PhpParser\Error;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;

final readonly class SourceFile
{
    /**
     * @param  list<string>  $lines
     * @param  array<Stmt>  $ast
     */
    public function __construct(
        public string $path,
        public string $code,
        public array $lines,
        public array $ast,
    ) {}

    public static function parse(string $path, string $code): self
    {
        $parser = new ParserFactory()->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code) ?? [];
        } catch (Error $error) {
            throw new InvalidArgumentException($error->getMessage(), 0, $error);
        }

        return new self($path, $code, explode("\n", $code), $ast);
    }

    public static function collapse(string $text): string
    {
        return preg_replace('/\s*\n\s*/', ' ', $text) ?? $text;
    }

    /**
     * Returns the bytes in [start, end), the unit of PhpParser file positions.
     */
    public function slice(int $start, int $end): string
    {
        return mb_substr($this->code, $start, $end - $start, '8bit');
    }

    /**
     * Returns the byte offset at which the given 1-based line starts.
     */
    public function lineStart(int $line): int
    {
        $offset = 0;

        foreach (array_slice($this->lines, 0, $line - 1) as $previous) {
            $offset += mb_strlen($previous, '8bit') + 1;
        }

        return $offset;
    }
}

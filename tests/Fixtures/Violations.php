<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class Violations
{
    public function __construct(private int $a, private int $b) {}

    public function params(int $a, int $b, int $c, int $d): void {}

    public function returns(int $a): int
    {
        if ($a === 1) {
            return 1;
        }

        if ($a === 2) {
            return 2;
        }

        if ($a === 3) {
            return 3;
        }

        return 4;
    }

    public function signature(int $firstLongParameterName, int $secondLongParameterName): void {}

    public function expressions(string $a, array $items): string
    {
        $closure = function (int $x): int {
            return $x;
        };

        foreach ($items ?: [] as $item) {
            $a = $a.$item;
        }

        throw_if($a === '', \LogicException::class, 'a message that is long enough');

        $b = match ($a) {
            'x', 'y' => 1,
            default => 2,
        };

        [$c, $d] = $this->pair();

        $sorted = static fn (int $x): int => $x + $d + $c;
        $e = \array_map(static function (int $x) use ($d, $c): int {
            return $x + $c + $d;
        }, [1]);
        $f = \in_array(\strtolower(\trim($a)), [$sorted($c), $e[0], $b, $d, 123456789], true);

        return strtoupper(trim(ltrim($a))).'Loading…'.$closure($b);
    }

    /**
     * @return array{int, int}
     */
    private function pair(): array
    {
        return [1, 2];
    }
}

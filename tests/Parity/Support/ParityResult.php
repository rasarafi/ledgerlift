<?php

namespace Tests\Parity\Support;

/**
 * What one parity run produced: the values the scenario returned on each side, and the
 * database differences left after intentional-differences.php.
 */
final readonly class ParityResult
{
    /**
     * @param  array<mixed>  $legacy
     * @param  array<mixed>  $new
     * @param  list<array{table: string, pk: ?string, column: string, legacy: mixed, new: mixed}>  $diff
     */
    public function __construct(
        public array $legacy,
        public array $new,
        public array $diff,
    ) {}
}

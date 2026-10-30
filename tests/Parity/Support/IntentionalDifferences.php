<?php

namespace Tests\Parity\Support;

use Closure;

/**
 * Removes the deliberate differences listed in intentional-differences.php from a DbDiff result.
 */
final class IntentionalDifferences
{
    /**
     * @return list<array{column: string, reason: string, scenario: ?string, match?: Closure(mixed, mixed): bool}>
     */
    public static function entries(): array
    {
        return require dirname(__DIR__).'/intentional-differences.php';
    }

    /**
     * @param  list<array{table: string, pk: ?string, column: string, legacy: mixed, new: mixed}>  $diff
     * @param  string  $test  The running test's name, matched against each entry's `scenario`.
     * @param  list<array{column: string, reason: string, scenario: ?string, match?: Closure(mixed, mixed): bool}>|null  $entries  Null means intentional-differences.php.
     * @return list<array{table: string, pk: ?string, column: string, legacy: mixed, new: mixed}>
     */
    public static function apply(array $diff, string $test, ?array $entries = null): array
    {
        $entries ??= self::entries();

        return array_values(array_filter($diff, function (array $difference) use ($entries, $test): bool {
            foreach ($entries as $entry) {
                if ($entry['column'] === $difference['table'].'.'.$difference['column']
                    && ($entry['scenario'] === null || str_contains($test, $entry['scenario']))
                    && (! isset($entry['match']) || ($entry['match'])($difference['legacy'], $difference['new']))) {
                    return false;
                }
            }

            return true;
        }));
    }
}

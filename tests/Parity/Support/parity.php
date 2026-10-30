<?php

use Pest\TestSuite;
use Tests\Parity\Support\AppClient;
use Tests\Parity\Support\DbDiff;
use Tests\Parity\Support\IntentionalDifferences;
use Tests\Parity\Support\ParityDatabases;
use Tests\Parity\Support\ParityResult;

/**
 * Runs `$scenario(AppClient $app, string $side)` against the legacy app and then the new
 * one, each on a freshly reset database (with `$settings` applied to `ip_settings`), and
 * diffs the two databases afterwards.
 *
 * @param  Closure(AppClient, string): (array<mixed>|null)  $scenario
 * @param  array<string, string>  $settings
 */
function parity(Closure $scenario, array $settings = []): ParityResult
{
    ParityDatabases::reset($settings);

    $legacy = $scenario(new AppClient((string) env('LEGACY_URL')), 'legacy') ?? [];
    $new = $scenario(new AppClient((string) env('NEW_URL')), 'new') ?? [];

    $test = TestSuite::getInstance()->test?->getPrintableTestCaseMethodName() ?? '';
    $diff = IntentionalDifferences::apply(
        DbDiff::compare(ParityDatabases::database('legacy'), ParityDatabases::database('new')),
        $test,
    );

    return new ParityResult($legacy, $new, $diff);
}

expect()->extend('toHaveNoDifferences', function () {
    /** @var ParityResult $result */
    $result = $this->value;

    expect($result->diff)->toBeEmpty("The databases differ after the scenario:\n".DbDiff::format($result->diff));

    return $this;
});

expect()->extend('toMatchAcrossApps', function () {
    /** @var ParityResult $result */
    $result = $this->value;

    expect($result->new)->toBe($result->legacy, 'The scenario returned different values on the new app (actual) than on legacy (expected).');

    return $this;
});

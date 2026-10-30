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

/**
 * A link as either app writes it, relative to the app: both hosts dropped (a link to a module ledgerlift
 * does not serve points at the legacy app) and a link's CSRF token masked. Two links differ on purpose
 * (tests/Parity/known-differences.md) and are compared as legacy writes them:
 * - "Invoices, PDFs": legacy's generate_pdf links carry its own token (`?_ip_csrf=`), ledgerlift's none;
 * - "Clients, links": a client's Create Quote is `href="#"` with a modal on legacy, and on ledgerlift a link
 *   to that client on the legacy app (the only link ledgerlift writes to a legacy `clients/view/{id}`).
 */
function parityHref(AppClient $app, string $href): string
{
    $legacy = rtrim((string) env('LEGACY_URL'), '/').'/';
    $own = rtrim($app->baseUrl, '/').'/';

    if ($own !== $legacy && preg_match('#^'.preg_quote($legacy, '#').'clients/view/\d+$#D', $href)) {
        return '#';
    }

    $relative = (string) preg_replace('#^(invoices/generate_pdf/\d+)\?_ip_csrf=[^&"\']*$#D', '$1', str_replace([$legacy, $own], '', $href));

    return (string) preg_replace('/_ip_csrf=[^&"\']*/', '_ip_csrf=TOKEN', $relative);
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

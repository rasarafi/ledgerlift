<?php

use App\Support\LegacyCrypt;

/*
 * Database differences between legacy and ledgerlift that are deliberate. Each entry names
 * one column, why it differs, and the scenario(s) it applies to (a substring of the test
 * name, or null for every scenario); an optional `match` callback narrows it to specific
 * values. Never a whole table. Differences outside the database (pages, headers, redirects)
 * are listed in known-differences.md instead.
 *
 * list<array{column: string, reason: string, scenario: ?string, match?: Closure(mixed, mixed): bool}>
 */

return [
    [
        'column' => 'ip_invoices.invoice_password',
        'reason' => 'Cryptor::encryptString() draws a random IV for every encryption, so the same password never encrypts to the same text, '
            .'on legacy itself either. Ledgerlift uses the same algorithm and key (App\\Support\\LegacyCrypt); the stored values match when both decrypt to the same password.',
        'scenario' => null,
        'match' => function (mixed $legacy, mixed $new): bool {
            $crypt = app(LegacyCrypt::class);

            return is_string($legacy) && is_string($new) && $crypt->decode($legacy) === $crypt->decode($new);
        },
    ],
];

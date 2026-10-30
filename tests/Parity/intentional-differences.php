<?php

/*
 * Database differences between legacy and ledgerlift that are deliberate. Each entry names
 * one column, why it differs, and the scenario(s) it applies to (a substring of the test
 * name, or null for every scenario); an optional `match` callback narrows it to specific
 * values. Never a whole table. Differences outside the database (pages, headers, redirects)
 * are listed in known-differences.md instead.
 *
 * list<array{column: string, reason: string, scenario: ?string, match?: Closure(mixed, mixed): bool}>
 */

return [];

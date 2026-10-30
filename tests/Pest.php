<?php

use Tests\Support\InteractsWithLegacySession;
use Tests\Support\RestoresBaseline;
use Tests\TestCase;

// Feature tests start from the baseline database (tests/Support/Baseline.php).
pest()->extend(TestCase::class)
    ->use(RestoresBaseline::class, InteractsWithLegacySession::class)
    ->in('Feature');

// The parity suite drives the two running apps over HTTP (see tests/Parity/Support).
pest()->extend(TestCase::class)
    ->beforeEach(function () {
        $missing = array_filter(['LEGACY_URL', 'NEW_URL', 'LEGACY_DB', 'NEW_DB'], fn (string $name) => ! env($name));

        if ($missing !== []) {
            $this->markTestSkipped(implode(', ', $missing).' unset; run `composer parity` with the compose stack up.');
        }
    })
    ->in('Parity');

require_once __DIR__.'/Parity/Support/parity.php';
require_once __DIR__.'/Parity/Support/invoices.php';

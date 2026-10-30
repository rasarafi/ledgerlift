<?php

namespace Tests\Support;

/**
 * Starts each test from the baseline database. Laravel's test case calls
 * `setUp<TraitName>()` on every trait a test uses.
 */
trait RestoresBaseline
{
    protected function setUpRestoresBaseline(): void
    {
        Baseline::restore();
    }
}

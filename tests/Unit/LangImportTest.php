<?php

use Tests\TestCase;

uses(TestCase::class);

it('serves the legacy English strings under the ip namespace', function () {
    expect(__('ip.invoice'))->toBe('Invoice');
    expect(__('ip.loginalert_credentials_incorrect'))->not->toBe('ip.loginalert_credentials_incorrect');
    expect(count(require lang_path('en/ip.php')))->toBeGreaterThan(800);
});

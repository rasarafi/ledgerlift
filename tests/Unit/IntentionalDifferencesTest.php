<?php

use Tests\Parity\Support\IntentionalDifferences;

$diff = [
    ['table' => 'ip_users', 'pk' => '1', 'column' => 'user_password', 'legacy' => 'md5', 'new' => 'bcrypt'],
    ['table' => 'ip_users', 'pk' => '1', 'column' => 'user_psalt', 'legacy' => '', 'new' => 'salt'],
    ['table' => 'ip_users', 'pk' => '2', 'column' => 'user_password', 'legacy' => 'a', 'new' => 'b'],
];

it('removes only the entries naming the exact column', function () use ($diff) {
    $entries = [['column' => 'ip_users.user_password', 'reason' => 'test', 'scenario' => null]];

    expect(IntentionalDifferences::apply($diff, 'any test', $entries))->toBe([$diff[1]]);
});

it('limits an entry to tests whose name contains its scenario', function () use ($diff) {
    $entries = [['column' => 'ip_users.user_psalt', 'reason' => 'test', 'scenario' => 'md5 user']];

    expect(IntentionalDifferences::apply($diff, 'logs in an md5 user', $entries))->toBe([$diff[0], $diff[2]])
        ->and(IntentionalDifferences::apply($diff, 'logs in the admin', $entries))->toBe($diff);
});

it('narrows an entry further with its match callback', function () use ($diff) {
    $entries = [[
        'column' => 'ip_users.user_password',
        'reason' => 'test',
        'scenario' => null,
        'match' => fn (mixed $legacy, mixed $new): bool => $legacy === 'md5',
    ]];

    expect(IntentionalDifferences::apply($diff, 'any test', $entries))->toBe([$diff[1], $diff[2]]);
});

it('ships entries that each name one column, a reason and a scenario', function () {
    expect(IntentionalDifferences::entries())->toBeList();

    foreach (IntentionalDifferences::entries() as $entry) {
        expect($entry['column'])->toMatch('/^ip_[a-z_]+\.[a-z_]+$/')
            ->and($entry['reason'])->not->toBeEmpty()
            ->and($entry)->toHaveKey('scenario');
    }
});

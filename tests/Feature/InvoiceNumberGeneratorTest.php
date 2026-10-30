<?php

use App\Services\InvoiceNumberGenerator;
use Illuminate\Support\Facades\DB;

/*
 * InvoiceNumberGenerator ports Mdl_invoice_groups::generate_invoice_number(): the group's
 * identifier format with its {{{tokens}}} replaced, then next_id + 1. The baseline has the
 * install's groups 3 ({{{id}}}, pad 0) and 4 (QUO{{{id}}}).
 */

function numberGroup(string $format, int $nextId, int $leftPad): int
{
    return DB::table('ip_invoice_groups')->insertGetId([
        'invoice_group_name' => 'Group', 'invoice_group_identifier_format' => $format,
        'invoice_group_next_id' => $nextId, 'invoice_group_left_pad' => $leftPad,
    ]);
}

function numberNextId(int $groupId): int
{
    return (int) DB::table('ip_invoice_groups')->where('invoice_group_id', $groupId)->value('invoice_group_next_id');
}

it('numbers the default group 3 with its bare id', function () {
    expect(app(InvoiceNumberGenerator::class)->generate(3))->toBe('1')
        ->and(numberNextId(3))->toBe(2)
        ->and(app(InvoiceNumberGenerator::class)->generate(3))->toBe('2')
        ->and(numberNextId(3))->toBe(3);
});

it('fills the year and the padded id of INV-{{{year}}}-{{{id}}}', function () {
    $group = numberGroup('INV-{{{year}}}-{{{id}}}', 1, 4);

    expect(app(InvoiceNumberGenerator::class)->generate($group))->toBe('INV-'.date('Y').'-0001')
        ->and(numberNextId($group))->toBe(2);
});

it('fills yy, month and day, drops unknown tokens and never cuts an id longer than the pad', function () {
    $group = numberGroup('{{{yy}}}{{{month}}}{{{day}}}/{{{nope}}}{{{id}}}{{{a|b}}}', 12345, 3);

    // `|` is outside the token pattern [^{|}]*, so {{{a|b}}} stays as written.
    expect(app(InvoiceNumberGenerator::class)->generate($group))->toBe(date('ymd').'/12345{{{a|b}}}')
        ->and(numberNextId($group))->toBe(12346);
});

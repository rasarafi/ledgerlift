<?php

use Tests\Parity\Support\AppClient;
use Tests\Parity\Support\LegacyModel;
use Tests\Parity\Support\ParityDatabases;
use Tests\Parity\Support\TableText;

/*
 * The invoices module, driven through the same endpoints on both apps as the browser drives them:
 * the create modal's invoices/ajax/create, the Save button's invoices/ajax/save with its items
 * JSON, the invoice tax modal's invoices/ajax/save_invoice_tax_rate and the item delete button's
 * invoices/ajax/delete_item. Every scenario diffs both databases whole, and compares the totals
 * of invoices/view/{id} and the rows of invoices/status/all.
 * Fixture: tax rates 1 VAT 15.00, 2 Reduced 5.00, 3 Service 7.50; clients 1-8; units 1 hour,
 * 2 month; groups 3 "{{{id}}}" and 5 "INV-{{{year}}}-{{{id}}}"; admin user 1.
 */

it('builds the reference invoice through the UI endpoints: create, items, invoice tax, discount', function () {
    $result = parity(function (AppClient $app, string $side) {
        $db = ParityDatabases::connection($side);
        $app->login();
        $create = iparCreate($app);
        $id = $create['id'];
        $steps = ['create' => $create['body'], 'view after create' => iparView($app, $id)];

        $steps['save items'] = iparSave($app, $id, ['invoice_number' => '1', 'invoice_status_id' => '2', 'items' => iparItems($id, IPAR_THREE_ITEMS)]);
        $steps['add tax'] = iparAddTax($app, $id, '3', '1');
        $steps['view after tax'] = iparView($app, $id);
        $steps['save discount'] = iparSave($app, $id, [
            'invoice_number' => '1', 'invoice_status_id' => '2', 'invoice_discount_percent' => '10', 'items' => iparItems($id, iparSavedItems($db, $id, IPAR_THREE_ITEMS)),
        ]);
        $steps['view'] = iparView($app, $id);
        $steps['list'] = iparList($app);
        $steps['stored'] = iparStored($db, $id);

        return $steps;
    });

    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy['create'])->toBe('{"success":1,"invoice_id":1}')
        ->and($result->legacy['view after create']['flash'])->toBe(['Record successfully created'])
        ->and($result->legacy['save items'])->toBe('{"success":1}')
        ->and($result->legacy['view'])->toMatchArray(['flash' => ['Record successfully updated'], 'discounts' => ['', '10.00']])
        ->and($result->legacy['stored']['amounts'])->toBe([
            'invoice_item_subtotal' => '1579.38', 'invoice_item_tax_total' => '191.99', 'invoice_tax_total' => '132.85',
            'invoice_total' => '1713.80', 'invoice_paid' => '0.00', 'invoice_balance' => '1713.80',
        ])
        ->and($result->legacy['view']['totals'])->toBe([
            ['Subtotal', '$1,579.38'], ['Item Tax', '$191.99'], ['Invoice Tax', 'Service 7.50% $132.85'], ['Global Discount', '$ %'],
            ['Total', '$1,713.80'], ['Paid', '$0.00'], ['Balance', '$1,713.80'],
        ]);
});

it('keeps only the percent of a combined discount, and casts the percent before standardising it', function () {
    $result = parity(function (AppClient $app, string $side) {
        $db = ParityDatabases::connection($side);
        $app->login();
        $id = iparCreate($app)['id'];
        $rows = [['item_name' => 'Consulting', 'item_quantity' => '1', 'item_price' => '100.00']];
        $steps = [];

        // Ajax.php:41-43: an amount posted with a percent is zeroed.
        $steps['both'] = iparSave($app, $id, ['invoice_number' => '1', 'items' => iparItems($id, $rows), 'invoice_discount_amount' => '5.00', 'invoice_discount_percent' => '2.5']);
        $steps['after both'] = [iparStored($db, $id), iparView($app, $id)];
        $rows = iparSavedItems($db, $id, $rows);
        $steps['amount'] = iparSave($app, $id, ['invoice_number' => '1', 'items' => iparItems($id, $rows), 'invoice_discount_amount' => '5.00']);
        $steps['after amount'] = [iparStored($db, $id), iparView($app, $id)];
        // Ajax.php:37: (float) "10,5" is 10 (Trap 5).
        $steps['comma percent'] = iparSave($app, $id, ['invoice_number' => '1', 'items' => iparItems($id, $rows), 'invoice_discount_percent' => '10,5']);
        $steps['after comma percent'] = [iparStored($db, $id), iparView($app, $id)];
        $steps['list'] = iparList($app);

        return $steps;
    });

    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy['after both'][0]['invoice'])->toMatchArray(['invoice_discount_amount' => '0.00', 'invoice_discount_percent' => '2.50'])
        ->and($result->legacy['after both'][0]['amounts']['invoice_total'])->toBe('97.50')
        ->and($result->legacy['after amount'][0]['amounts']['invoice_total'])->toBe('95.00')
        ->and($result->legacy['after comma percent'][0]['invoice'])->toMatchArray(['invoice_discount_amount' => '0.00', 'invoice_discount_percent' => '10.00'])
        ->and($result->legacy['after comma percent'][0]['amounts']['invoice_total'])->toBe('90.00');
});

it('rounds on write: 0.333 × 10.00 with VAT 15, and 3 × 0.35 with Service 7.50', function () {
    $result = parity(function (AppClient $app, string $side) {
        $db = ParityDatabases::connection($side);
        $app->login();
        $steps = [];

        foreach ([['Cable', '0.333', '10.00', '1'], ['Washers', '3', '0.35', '3']] as [$name, $quantity, $price, $taxRateId]) {
            $id = iparCreate($app)['id'];
            iparSave($app, $id, ['invoice_number' => (string) $id, 'items' => iparItems($id, [['item_name' => $name, 'item_quantity' => $quantity, 'item_price' => $price, 'item_tax_rate_id' => $taxRateId]])]);
            $steps[$name] = [iparStored($db, $id), iparView($app, $id)];
        }

        $steps['list'] = iparList($app);

        return $steps;
    });

    // 0.333 × 10 = 3.33, tax 0.4995 → 0.50, total 3.8295 → 3.83; 3 × 0.35 = 1.05, tax 0.07875 → 0.08, total 1.12875 → 1.13.
    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy['Cable'][0]['items'])->toBe([['item_name' => 'Cable', 'item_subtotal' => '3.33', 'item_tax_total' => '0.50', 'item_discount' => '0.00', 'item_total' => '3.83']])
        ->and($result->legacy['Washers'][0]['items'])->toBe([['item_name' => 'Washers', 'item_subtotal' => '1.05', 'item_tax_total' => '0.08', 'item_discount' => '0.00', 'item_total' => '1.13']])
        ->and($result->legacy['Cable'][1]['items']['quantities'])->toBe(['0.33']);
});

it('deletes an item and recalculates; an unknown item is not deleted', function () {
    $result = parity(function (AppClient $app, string $side) {
        $db = ParityDatabases::connection($side);
        $app->login();
        $id = iparCreate($app)['id'];
        iparSave($app, $id, ['invoice_number' => '1', 'items' => iparItems($id, IPAR_THREE_ITEMS)]);
        $itemId = (int) $db->table('ip_invoice_items')->where('item_name', 'Hinges')->value('item_id');
        $delete = fn (string $path, array $post) => $app->post($path, $post, ajax: true)->body();

        $steps = [
            'unknown item' => $delete("invoices/ajax/delete_item/$id", ['item_id' => '9999']),
            'unknown invoice' => $delete('invoices/ajax/delete_item/9999', ['item_id' => (string) $itemId]),
            'no item' => $delete("invoices/ajax/delete_item/$id", []),
            'delete' => $delete("invoices/ajax/delete_item/$id", ['item_id' => (string) $itemId]),
            'stored' => iparStored($db, $id),
            'view' => iparView($app, $id),
            'list' => iparList($app),
        ];

        return $steps;
    });

    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and(array_slice($result->legacy, 0, 4))->toBe(['unknown item' => '{"success":0}', 'unknown invoice' => '{"success":0}', 'no item' => '{"success":0}', 'delete' => '{"success":1}'])
        ->and($result->legacy['view']['flash'])->toBe(['Record successfully deleted'])
        ->and($result->legacy['stored']['amounts'])->toMatchArray(['invoice_item_subtotal' => '1357.50', 'invoice_total' => '1537.50']);
});

it('deletes an invoice tax: ledgerlift for real, legacy by its model-level delete and a recalculation', function () {
    $answers = [];

    $result = parity(function (AppClient $app, string $side) use (&$answers) {
        $db = ParityDatabases::connection($side);
        $app->login();
        $id = iparCreate($app)['id'];
        $rows = [['item_name' => 'Survey', 'item_quantity' => '2', 'item_price' => '250.00', 'item_tax_rate_id' => '1']];
        iparSave($app, $id, ['invoice_number' => '1', 'items' => iparItems($id, $rows)]);
        iparAddTax($app, $id, '3', '0');
        iparAddTax($app, $id, '2', '1');
        $steps = ['before' => iparView($app, $id)];
        $taxId = (int) $db->table('ip_invoice_tax_rates')->where('tax_rate_id', 3)->value('invoice_tax_rate_id');

        $delete = $app->post("invoices/delete_invoice_tax/$id/$taxId");
        $answers[$side] = [$delete->status(), $app->location(), iparView($app, $id)['flash']];

        if ($side === 'legacy') {
            // Invoices::delete_invoice_tax() (Invoices.php:361-367): the row, then Mdl_invoice_amounts::calculate().
            // Legacy recalculates from its own code: the Save button with nothing changed runs the same calculate().
            LegacyModel::deleteInvoiceTaxRate($db, $taxId);
            iparSave($app, $id, ['invoice_number' => '1', 'items' => iparItems($id, iparSavedItems($db, $id, $rows))]);
        }

        $steps['after'] = iparView($app, $id);
        unset($steps['after']['flash']);
        $steps['stored'] = iparStored($db, $id);
        $steps['list'] = iparList($app);

        return $steps;
    });

    expect($answers['legacy'])->toBe([303, 'invoices/view/1', ['invalid_request']])
        ->and($answers['new'])->toBe([303, 'invoices/view/1', ['Record successfully deleted']]);
    // 500 + VAT 75; Service 7.50% of 500 = 37.50 (before item tax) goes, Reduced 5% of 575 = 28.75 stays.
    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy['stored']['taxes'])->toBe(['28.75'])
        ->and($result->legacy['stored']['amounts'])->toMatchArray(['invoice_tax_total' => '28.75', 'invoice_total' => '603.75']);
});

it('sends an invoice through ajax/save, numbering it when it has no number, and makes it read-only at read_only_toggle', function (array $settings) {
    $result = parity(function (AppClient $app, string $side) {
        $db = ParityDatabases::connection($side);
        $app->login();
        // The PDF password is stored encrypted (see intentional-differences.php) and shown decrypted.
        $id = iparCreate($app, ['invoice_password' => 'pdf & "pass"'])['id'];
        $number = (string) $db->table('ip_invoices')->where('invoice_id', $id)->value('invoice_number');
        $rows = [['item_name' => 'Retainer', 'item_quantity' => '1', 'item_price' => '300.00']];
        $steps = ['created' => [iparStored($db, $id), iparView($app, $id)]];

        $steps['draft'] = iparSave($app, $id, ['invoice_number' => $number, 'invoice_password' => 'pdf & "pass"', 'items' => iparItems($id, $rows)]);
        $steps['after draft'] = [iparStored($db, $id), iparView($app, $id)];
        $rows = iparSavedItems($db, $id, $rows);
        $steps['sent'] = iparSave($app, $id, ['invoice_number' => $number, 'invoice_status_id' => '2', 'items' => iparItems($id, $rows), 'invoice_terms' => 'Net 30 <b>days</b>']);
        $steps['after sent'] = [iparStored($db, $id), iparView($app, $id), iparList($app), iparList($app, 'invoices/status/sent')];
        $number = (string) $db->table('ip_invoices')->where('invoice_id', $id)->value('invoice_number');
        $steps['paid'] = iparSave($app, $id, ['invoice_number' => $number, 'invoice_status_id' => '4', 'items' => iparItems($id, $rows)]);
        $steps['after paid'] = [iparStored($db, $id), iparView($app, $id), iparList($app, 'invoices/status/paid')];

        return $steps;
    }, $settings);

    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy['after sent'][0]['invoice'])->toMatchArray(['invoice_number' => '1', 'invoice_status_id' => 2, 'is_read_only' => null])
        ->and($result->legacy['after paid'][0]['invoice'])->toMatchArray(['invoice_status_id' => 4, 'is_read_only' => 1])
        ->and($result->legacy['after paid'][1]['labels'])->toBe(['Read only'])
        ->and($result->legacy['created'][1]['password'])->toBe('pdf & "pass"')
        ->and($result->legacy['after sent'][1]['password'])->toBe('');

    if ($settings !== []) {
        expect($result->legacy['after draft'][0]['invoice']['invoice_number'])->toBe('')
            ->and($result->legacy['after draft'][1]['title'])->toBe(['Invoice ID: 1']);
    }
})->with([
    'default settings' => [[]],
    'generate_invoice_number_for_draft = 0' => [['generate_invoice_number_for_draft' => '0']],
]);

it('makes a settled invoice with two items read-only within one save, through the per-item recalculation', function (int $items) {
    $result = parity(function (AppClient $app, string $side) use ($items) {
        $db = ParityDatabases::connection($side);
        $app->login();
        $id = iparCreate($app)['id'];
        $rows = array_fill(0, $items, ['item_name' => 'Part', 'item_quantity' => '1', 'item_price' => (string) (100 / $items)]);
        iparSave($app, $id, ['invoice_number' => '1', 'invoice_status_id' => '2', 'items' => iparItems($id, $rows)]);
        // A payment of the whole total, as SQL: payments are not part of this scenario.
        $db->table('ip_payments')->insert(['invoice_id' => $id, 'payment_method_id' => 3, 'payment_date' => '2026-10-02', 'payment_amount' => '100.00', 'payment_note' => '']);

        // Ajax::save() recalculates after every item (Mdl_items::save()): the first settles it (2 → 4), the
        // second sees 4 = read_only_toggle and sets read-only; the posted 2 and the trailing recalculation follow.
        $answer = iparSave($app, $id, ['invoice_number' => '1', 'invoice_status_id' => '2', 'items' => iparItems($id, iparSavedItems($db, $id, $rows))]);

        return ['answer' => $answer, 'stored' => iparStored($db, $id), 'view' => iparView($app, $id), 'list' => iparList($app)];
    });

    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy['stored']['invoice'])->toMatchArray(['invoice_status_id' => 4, 'is_read_only' => $items > 1 ? 1 : null, 'payment_method' => 3]);
})->with([
    'one item' => [1],
    'two items' => [2],
]);

it('refuses a duplicate number with the same validation error and no change, and echoes the save quirks', function () {
    $result = parity(function (AppClient $app, string $side) {
        $db = ParityDatabases::connection($side);
        $app->login();
        $first = iparCreate($app)['id'];
        $second = iparCreate($app)['id'];
        $before = iparStored($db, $second);

        $steps = [
            'duplicate' => iparSave($app, $second, ['invoice_number' => '1', 'invoice_terms' => 'changed', 'items' => iparItems($second, [['item_name' => 'Kept out', 'item_quantity' => '1', 'item_price' => '9']])]),
            'unchanged' => iparStored($db, $second) === $before,
            'no dates' => iparSave($app, $second, ['invoice_date_due' => '', 'invoice_date_created' => ' ', 'invoice_number' => '2']),
            // Ajax.php:97-111 and :123-134 echo their error and carry on saving.
            'nameless item' => iparSave($app, $second, ['invoice_number' => '2', 'items' => iparItems($second, [['item_price' => '5'], ['item_name' => 'Named', 'item_quantity' => '2', 'item_price' => '5']])]),
            'invalid number' => iparSave($app, $first, ['invoice_number' => 'INV"1', 'items' => '[]']),
            'stored' => [iparStored($db, $first), iparStored($db, $second)],
            'views' => [iparView($app, $first), iparView($app, $second)],
            'list' => iparList($app),
        ];

        return $steps;
    });

    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy['duplicate'])->toBe('{"success":0,"validation_errors":{"invoice_number":"<p>The Invoice # field must contain a unique value.<\/p>"}}')
        ->and($result->legacy['unchanged'])->toBeTrue()
        ->and($result->legacy['nameless item'])->toBe('{"success":0,"validation_errors":{"item_name":"<p>The Item field is required.<\/p>"}}{"success":1}')
        ->and($result->legacy['invalid number'])->toBe('{"success":0,"validation_errors":{"invoice_number":"invoice_number contains invalid characters"}}{"success":1}')
        ->and($result->legacy['stored'][0]['invoice']['invoice_number'])->toBe('INV"1');
});

it('deletes a draft: ledgerlift for real, legacy by its model-level delete; a sent invoice is refused', function () {
    $answers = [];

    $result = parity(function (AppClient $app, string $side) use (&$answers) {
        $db = ParityDatabases::connection($side);
        $app->login();
        $draft = iparCreate($app)['id'];
        $sent = iparCreate($app)['id'];
        iparSave($app, $draft, ['invoice_number' => '1', 'items' => iparItems($draft, IPAR_THREE_ITEMS)]);
        iparAddTax($app, $draft, '3', '1');
        iparSave($app, $sent, ['invoice_number' => '2', 'invoice_status_id' => '2', 'items' => iparItems($sent, [['item_name' => 'Kept', 'item_quantity' => '1', 'item_price' => '10']])]);
        $steps = ['before' => iparList($app)];

        // The flash outlives one redirect only; the invoice view (which renders alerts) shows it.
        $delete = $app->post("invoices/delete/$draft");
        $answers[$side]['draft'] = [$delete->status(), $app->location(), iparView($app, $sent)['flash']];

        if ($side === 'legacy') {
            LegacyModel::deleteInvoice($db, $draft);
        }

        $delete = $app->post("invoices/delete/$sent");
        $answers[$side]['sent'] = [$delete->status(), $app->location(), iparView($app, $sent)['flash']];
        $answers[$side]['over GET'] = $app->get("invoices/delete/$sent")->status();

        $steps['after'] = iparList($app);
        $steps['deleted view'] = iparView($app, $draft);
        $steps['counts'] = array_map(fn (string $table) => $db->table($table)->count(), ['ip_invoices', 'ip_invoice_amounts', 'ip_invoice_items', 'ip_invoice_item_amounts', 'ip_invoice_tax_rates']);

        return $steps;
    });

    expect($answers['legacy'])->toBe([
        'draft' => [303, 'invoices/index', ['invalid_request']], 'sent' => [303, 'invoices/index', ['invalid_request']], 'over GET' => 404,
    ])->and($answers['new'])->toBe([
        'draft' => [303, 'invoices/index', ['Record successfully deleted']],
        'sent' => [303, 'invoices/index', ['Deleting invoices is forbidden. Please contact the administrator or consult the documentation.']],
        'over GET' => 404,
    ]);
    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy['deleted view'])->toBe(['status' => 404, 'location' => null])
        ->and($result->legacy['counts'])->toBe([1, 1, 1, 1, 0]);
});

it('lists every status with overdue invoices, and an invoice two quotes were converted into twice', function (array $settings) {
    $result = parity(function (AppClient $app, string $side) {
        $db = ParityDatabases::connection($side);
        $app->login();
        $rows = [['item_name' => 'Hosting', 'item_quantity' => '1', 'item_price' => '19.90']];
        // [client, status, due date]: two overdue (sent and viewed, due in the past), one sent in time, a draft and a paid one.
        foreach ([[1, '2', '01/15/2020'], [2, '3', '02/01/2020'], [3, '2', '12/31/2099'], [4, '1', '01/01/2020'], [5, '4', '01/01/2020']] as [$client, $status, $due]) {
            $id = iparCreate($app, ['client_id' => (string) $client])['id'];
            iparSave($app, $id, ['invoice_number' => (string) $id, 'invoice_status_id' => $status, 'invoice_date_due' => $due, 'items' => iparItems($id, $rows)]);
        }

        // Two quotes converted into invoice 3, as the quote module would leave them.
        foreach ([60, 61] as $quoteId) {
            $db->table('ip_quotes')->insert([
                'quote_id' => $quoteId, 'invoice_id' => 3, 'client_id' => 3, 'user_id' => 1, 'invoice_group_id' => 4, 'quote_status_id' => 4,
                'quote_date_created' => '2026-09-01', 'quote_date_modified' => '2026-09-01 10:00:00', 'quote_date_expires' => '2026-10-01', 'quote_url_key' => md5("q$quoteId"),
            ]);
        }

        $steps = [];

        foreach (['all', 'draft', 'sent', 'viewed', 'paid', 'overdue', 'all/2', 'unknown', ''] as $status) {
            $steps[$status] = iparList($app, rtrim("invoices/status/$status", '/'));
        }

        $steps['filter'] = TableText::rows($app->post('filter/ajax/filter_invoices', ['filter_query' => 'bellmont 19.90'], ajax: true)->body());
        $steps['view of 3'] = iparView($app, 3);

        return $steps;
    }, $settings);

    expect($result)->toHaveNoDifferences()->toMatchAcrossApps();

    if ($settings === []) {
        expect(array_column(array_slice($result->legacy['overdue']['rows'], 1), 1))->toBe(['2', '1'])
            ->and(array_count_values(array_column(array_slice($result->legacy['all']['rows'], 1), 1)))->toMatchArray([3 => 2]);
    }
})->with([
    'default settings' => [[]],
    'two per page' => [['default_list_limit' => '2']],
]);

it('deletes a client with invoices made through the apps: ledgerlift for real, legacy by its model-level delete', function () {
    $answers = [];

    $result = parity(function (AppClient $app, string $side) use (&$answers) {
        $db = ParityDatabases::connection($side);
        $app->login();

        foreach ([2, 2, 1] as $client) {
            $id = iparCreate($app, ['client_id' => (string) $client])['id'];
            iparSave($app, $id, ['invoice_number' => (string) $id, 'invoice_status_id' => '2', 'items' => iparItems($id, IPAR_THREE_ITEMS)]);
            iparAddTax($app, $id, '3', '1');
        }

        $delete = $app->post('clients/delete/2');
        $answers[$side] = [$delete->status(), $app->location()];

        if ($side === 'legacy') {
            LegacyModel::deleteClient($db, 2);
        }

        $steps = ['list' => iparList($app)];
        $steps['counts'] = array_map(fn (string $table) => $db->table($table)->count(), ['ip_clients', 'ip_invoices', 'ip_invoice_amounts', 'ip_invoice_items', 'ip_invoice_item_amounts', 'ip_invoice_tax_rates']);

        return $steps;
    });

    expect($answers['new'])->toBe([303, 'clients'])
        ->and($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy['counts'])->toBe([7, 1, 1, 3, 3, 1]);
});

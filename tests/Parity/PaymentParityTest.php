<?php

use Illuminate\Database\Connection;
use Tests\Parity\Support\AppClient;
use Tests\Parity\Support\LegacyModel;
use Tests\Parity\Support\ParityDatabases;
use Tests\Parity\Support\TableText;

/*
 * The payments module, driven through the same endpoints on both apps as the browser drives them:
 * the add-payment modal (payments/ajax/modal_add_payment, then payments/ajax/add), the payment form
 * and the payment list. The invoices are built through the invoice endpoints, as in
 * InvoiceParityTest. Every scenario diffs both databases whole, and compares the totals of
 * invoices/view/{id}, the invoice list and the payment list.
 * Fixture: tax rates 1 VAT 15.00, 2 Reduced 5.00, 3 Service 7.50; payment methods 1 Cash, 2 Credit Card;
 * clients 1-8; admin user 1.
 */

/** The same path on either app, as the apps' own links write it. */
function ppayRelative(AppClient $app, string $href): string
{
    return (string) preg_replace('/_ip_csrf=[^&"\']*/', '_ip_csrf=TOKEN', str_replace([rtrim((string) env('LEGACY_URL'), '/').'/', rtrim($app->baseUrl, '/').'/'], '', $href));
}

/**
 * The Task 10b invoice, sent, as Task 11a's first scenario builds it (total 1713.80, items VAT/Reduced/none,
 * Service 7.50% invoice tax, 10% discount).
 */
function ppayInvoice(AppClient $app, Connection $db, array $create = []): int
{
    $id = iparCreate($app, $create)['id'];
    iparSave($app, $id, ['invoice_number' => (string) $id, 'invoice_status_id' => '2', 'items' => iparItems($id, IPAR_THREE_ITEMS)]);
    iparAddTax($app, $id, '3', '1');
    iparSave($app, $id, [
        'invoice_number' => (string) $id, 'invoice_status_id' => '2', 'invoice_discount_percent' => '10', 'items' => iparItems($id, iparSavedItems($db, $id, IPAR_THREE_ITEMS)),
    ]);

    return $id;
}

/**
 * The modal's post (views/payments/modal_add_payment.php:13-19): the raw answer.
 *
 * @param  array<string, string>  $post
 */
function ppayAdd(AppClient $app, int $invoiceId, string $amount, array $post = []): string
{
    return $app->post('payments/ajax/add', $post + [
        'invoice_id' => (string) $invoiceId, 'payment_amount' => $amount, 'payment_method_id' => '', 'payment_date' => '10/15/2026', 'payment_note' => '',
    ], ajax: true)->body();
}

/** The modal the "Enter payment" link opens: it posts the invoice's id, balance and method (views/layout/includes/head.php:97-108). */
function ppayModal(AppClient $app, Connection $db, int $invoiceId): array
{
    $invoice = $db->table('ip_invoices')->join('ip_invoice_amounts', 'ip_invoice_amounts.invoice_id', '=', 'ip_invoices.invoice_id')
        ->where('ip_invoices.invoice_id', $invoiceId)->first(['invoice_balance', 'payment_method']);
    $response = $app->post('payments/ajax/modal_add_payment', [
        'invoice_id' => (string) $invoiceId, 'invoice_balance' => $invoice->invoice_balance, 'invoice_payment_method' => (string) $invoice->payment_method, 'payment_cf_exist' => 'no',
    ], ajax: true);
    preg_match('/window\.location = (".*?");/', $response->body(), $redirect);

    return [
        'status' => $response->status(),
        'type' => strtolower($response->header('Content-Type')),
        'title' => $app->texts('//h3'),
        'inputs' => $app->texts('//div[@id="enter-payment"]//input/@id | //div[@id="enter-payment"]//input/@value'),
        'amount' => $app->text('//input[@id="payment_amount"]/@value'),
        'date' => $app->text('//input[@id="payment_date"]/@value'),
        'method' => [
            'disabled' => $app->texts('//select[@id="payment_method_id"]/@disabled'),
            'options' => $app->texts('//select[@id="payment_method_id"]/option/@value'),
            'labels' => $app->texts('//select[@id="payment_method_id"]/option'),
            'selected' => $app->texts('//select[@id="payment_method_id"]/option[@selected]/@value'),
        ],
        'labels' => $app->texts('//div[@id="enter-payment"]//label'),
        'buttons' => $app->texts('//div[@class="modal-footer"]//button'),
        'cf exist' => $app->text('//input[@id="payment_cf_exist"]/@value'),
        'redirect' => ppayRelative($app, json_decode($redirect[1] ?? '""', true)),
    ];
}

/** The rows, links and pager of the payment list (or the filter's rows), and its flash. */
function ppayList(AppClient $app, string $path = 'payments/index'): array
{
    $response = $app->get($path);

    return [
        'status' => $response->status(),
        'location' => $app->location(),
        'title' => $app->texts('//h1[@class="headerbar-title"]'),
        'flash' => $app->texts('//div[contains(@class, "alert-dismissible")]/span'),
        'rows' => TableText::rows($response->body(), '//div[@id="filter_results"]//table'),
        'links' => array_map(fn (string $href) => ppayRelative($app, $href), $app->texts('//div[@id="content"]//a/@href | //div[@id="filter_results"]//form/@action | //div[@id="headerbar"]//a/@href')),
        'search box' => $app->texts('//input[@id="filter"]/@placeholder'),
    ];
}

/** The payment form: its title, flash and errors, its fields, and the amounts and methods its script switches between. */
function ppayForm(AppClient $app, string $path = 'payments/form'): array
{
    $response = $app->get($path);

    if ($response->status() !== 200) {
        return ['status' => $response->status(), 'location' => $app->location()];
    }

    return ppayFormPage($app, $response->body());
}

/** @return array<string, mixed> The form on the page `$app` has just loaded. */
function ppayFormPage(AppClient $app, string $body): array
{
    preg_match("/amounts = json_parse\\('(.*?)'/", $body, $amounts);
    preg_match("/invoice_payment_methods = json_parse\\('(.*?)'/", $body, $methods);

    return [
        'status' => 200,
        'title' => $app->texts('//h1[@class="headerbar-title"]'),
        'flash' => $app->texts('//div[contains(@class, "alert")]'),
        'buttons' => $app->texts('//div[@id="headerbar"]//button/@name'),
        'invoice' => ['options' => $app->texts('//select[@id="invoice_id"]/option/@value'), 'labels' => $app->texts('//select[@id="invoice_id"]/option'), 'selected' => $app->texts('//select[@id="invoice_id"]/option[@selected]/@value')],
        'date' => $app->text('//input[@id="payment_date"]/@value'),
        'amount' => $app->text('//input[@id="payment_amount"]/@value'),
        'method' => [
            'hidden' => $app->texts('//div[contains(@class, "payment-method-wrapper")]/input/@value'),
            'disabled' => $app->texts('//select[@id="payment_method_id"]/@disabled'),
            'options' => $app->texts('//select[@id="payment_method_id"]/option/@value'),
            'labels' => $app->texts('//select[@id="payment_method_id"]/option'),
            'selected' => $app->texts('//select[@id="payment_method_id"]/option[@selected]/@value'),
        ],
        'note' => $app->texts('//textarea[@name="payment_note"]'),
        'payment id' => $app->texts('//input[@name="payment_id"]/@value'),
        'amounts' => json_decode($amounts[1] ?? 'null', true),
        'methods' => json_decode($methods[1] ?? 'null', true),
    ];
}

/** Every payment and the state of every invoice, as stored. */
function ppayStored(Connection $db): array
{
    return [
        'payments' => $db->table('ip_payments')->orderBy('payment_id')->get()->map(fn (object $row) => (array) $row)->all(),
        'invoices' => $db->table('ip_invoices')->join('ip_invoice_amounts', 'ip_invoice_amounts.invoice_id', '=', 'ip_invoices.invoice_id')->orderBy('ip_invoices.invoice_id')
            ->get(['ip_invoices.invoice_id', 'invoice_status_id', 'is_read_only', 'payment_method', 'invoice_total', 'invoice_paid', 'invoice_balance'])
            ->map(fn (object $row) => (array) $row)->all(),
    ];
}

/** The invoice's view and the lists a payment changes. */
function ppayState(AppClient $app, Connection $db, int $invoiceId): array
{
    return ['stored' => ppayStored($db), 'view' => iparView($app, $invoiceId), 'invoices' => iparList($app), 'payments' => ppayList($app)];
}

it('takes payment 500.00, then 1213.80: paid, read-only, and the lists, modal and forms agree', function (array $settings) {
    $result = parity(function (AppClient $app, string $side) {
        $db = ParityDatabases::connection($side);
        $app->login();
        $id = ppayInvoice($app, $db);
        $steps = ['modal' => ppayModal($app, $db, $id), 'blank form' => ppayForm($app)];

        $steps['first'] = ppayAdd($app, $id, '500.00', ['payment_method_id' => '2', 'payment_note' => 'Deposit <b>1</b>']);
        $steps['after first'] = ppayState($app, $db, $id);
        $steps['modal after first'] = ppayModal($app, $db, $id);
        $steps['second'] = ppayAdd($app, $id, '1213.80', ['payment_method_id' => '1', 'payment_date' => '10/20/2026']);
        $steps['after second'] = ppayState($app, $db, $id);
        $steps['edit form'] = ppayForm($app, 'payments/form/1');
        $steps['edit form 2'] = ppayForm($app, 'payments/form/2');
        $steps['no open invoice'] = ppayForm($app);
        $steps['filter'] = [
            TableText::rows($app->post('filter/ajax/filter_payments', ['filter_query' => 'deposit'], ajax: true)->body()),
            TableText::rows($app->post('filter/ajax/filter_payments', ['filter_query' => 'CREDIT 2026-10-15 nomatch'], ajax: true)->body()),
            TableText::rows($app->post('filter/ajax/filter_payments', ['filter_query' => '500.00 ash'], ajax: true)->body()),
        ];

        return $steps;
    }, $settings);

    // 1713.80 − 500.00 = 1213.80; the second payment settles it: status 4 from the first pass, read-only from the second.
    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy['first'])->toBe('{"success":1,"payment_id":1}')
        ->and($result->legacy['second'])->toBe('{"success":1,"payment_id":2}')
        ->and($result->legacy['after first']['stored']['invoices'][0])->toMatchArray([
            'invoice_status_id' => 2, 'is_read_only' => null, 'payment_method' => 0, 'invoice_paid' => '500.00', 'invoice_balance' => '1213.80',
        ])
        ->and($result->legacy['after second']['stored']['invoices'][0])->toMatchArray([
            'invoice_status_id' => 4, 'is_read_only' => 1, 'payment_method' => 2, 'invoice_paid' => '1713.80', 'invoice_balance' => '0.00',
        ])
        ->and($result->legacy['after second']['view']['labels'])->toBe(['Read only'])
        ->and($result->legacy['after second']['payments']['rows'][1])->toBe(['10/20/2026', '10/01/2026', '1', 'Ashgrove Joinery', $settings === [] ? '$1,213.80' : '1,213.80 €', 'Cash', '', 'Options Edit Delete']);
})->with([
    'default settings' => [[]],
    'euro after the amount' => [['currency_symbol' => '€', 'currency_symbol_placement' => 'afterspace']],
]);

it('rejects an overpayment with the same error JSON, accepts zero and negative amounts, and answers crafted posts alike', function () {
    $result = parity(function (AppClient $app, string $side) {
        $db = ParityDatabases::connection($side);
        $app->login();
        $id = ppayInvoice($app, $db);
        $before = ppayStored($db);

        $steps = [
            // Review Focus 3.
            'overpayment' => ppayAdd($app, $id, '2000.00'),
            'just over' => ppayAdd($app, $id, '1713.81'),
            'unchanged' => ppayStored($db) === $before,
            'zero' => ppayAdd($app, $id, '0', ['payment_note' => 'zero']),
            'after zero' => ppayState($app, $db, $id),
            'negative' => ppayAdd($app, $id, '-25.5'),
            'after negative' => ppayState($app, $db, $id),
            // The 25.50 the negative payment gave back is payable again.
            'exact balance plus refund' => ppayAdd($app, $id, '1739.30'),
            'missing fields' => $app->post('payments/ajax/add', ['payment_date' => '', 'invoice_id' => '', 'payment_amount' => ''], ajax: true)->body(),
            'only an amount' => $app->post('payments/ajax/add', ['payment_amount' => '5'], ajax: true)->body(),
            'unknown invoice' => ppayAdd($app, 9999, '5'),
            'text amount' => ppayAdd($app, $id, 'abc'),
            'array note' => ppayAdd($app, $id, '1', ['payment_note' => ['x', 'y']]),
            // Mdl_payments.php:145: paid >= total (0 >= 0) sets status paid directly, from a draft; the calculator alone would not.
            'zero invoice' => (function () use ($app, $db) {
                $zero = iparCreate($app)['id'];

                return [ppayAdd($app, $zero, '0'), ppayState($app, $db, $zero)];
            })(),
            'final' => ppayState($app, $db, $id),
        ];

        return $steps;
    });

    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy['zero invoice'][1]['stored']['invoices'][1])->toMatchArray(['invoice_status_id' => 4, 'is_read_only' => null, 'invoice_total' => '0.00'])
        ->and($result->legacy['overpayment'])->toBe('{"success":0,"validation_errors":{"payment_amount":"<p>Payment amount cannot exceed invoice balance.<\/p>"}}')
        ->and($result->legacy['just over'])->toBe('{"success":0,"validation_errors":{"payment_amount":"<p>Payment amount cannot exceed invoice balance.<\/p>"}}')
        ->and($result->legacy['unchanged'])->toBeTrue()
        ->and($result->legacy['zero'])->toBe('{"success":1,"payment_id":1}')
        ->and($result->legacy['after negative']['stored']['invoices'][0])->toMatchArray(['invoice_paid' => '-25.50', 'invoice_balance' => '1739.30', 'invoice_status_id' => 2]);
});

it('deletes a payment: ledgerlift for real, legacy by its model-level delete; paid goes back to sent and read-only stays', function () {
    $answers = [];

    $result = parity(function (AppClient $app, string $side) use (&$answers) {
        $db = ParityDatabases::connection($side);
        $app->login();
        $id = ppayInvoice($app, $db);
        $viewed = iparCreate($app)['id'];
        // A zero-price placeholder item gives legacy a recalculation to run (invoices/ajax/delete_item).
        iparSave($app, $viewed, ['invoice_number' => 'V', 'invoice_status_id' => '3', 'items' => iparItems($viewed, [
            ['item_name' => 'Retainer', 'item_quantity' => '1', 'item_price' => '100.00'], ['item_name' => 'Placeholder', 'item_quantity' => '1', 'item_price' => '0.00'],
        ])]);
        $placeholders = [$id => 0, $viewed => (int) $db->table('ip_invoice_items')->where('item_name', 'Placeholder')->value('item_id')];
        iparSave($app, $id, [
            'invoice_number' => (string) $id, 'invoice_status_id' => '2', 'invoice_discount_percent' => '10',
            'items' => iparItems($id, [...iparSavedItems($db, $id, IPAR_THREE_ITEMS), ['item_name' => 'Placeholder', 'item_quantity' => '1', 'item_price' => '0.00']]),
        ]);
        $placeholders[$id] = (int) $db->table('ip_invoice_items')->where('item_name', 'Placeholder')->where('invoice_id', $id)->value('item_id');

        ppayAdd($app, $id, '500.00', ['payment_method_id' => '2']);
        ppayAdd($app, $id, '1213.80');
        ppayAdd($app, $viewed, '30.00');
        ppayAdd($app, $viewed, '20.00');
        $db->table('ip_payment_custom')->insert(['payment_id' => 2, 'payment_custom_fieldid' => 1, 'payment_custom_fieldvalue' => 'x']);
        $steps = ['before' => ppayState($app, $db, $id)];

        foreach ([[2, $id], [3, $viewed]] as [$paymentId, $invoiceId]) {
            $deletePlaceholder = fn () => $app->post("invoices/ajax/delete_item/$invoiceId", ['item_id' => (string) $placeholders[$invoiceId]], ajax: true)->json('success');

            if ($side === 'new') {
                // Only ledgerlift's delete may recalculate after the payment goes, so the placeholder is removed (and the invoice
                // recalculated with the payment still there) before it: a missing recalculation or 4 → 2 shows in the diff.
                expect($deletePlaceholder())->toBe(1);
            }

            $delete = $app->post("payments/delete/$paymentId");
            $answers[$side][$paymentId] = [$delete->status(), $app->location(), ppayList($app)['flash']];

            if ($side === 'legacy') {
                // Mdl_payments::delete() (Mdl_payments.php:171-201): the row, legacy's own recalculation (here the placeholder's
                // invoices/ajax/delete_item), 4 → 2, then delete_orphans().
                LegacyModel::deletePayment($db, $paymentId);
                expect($deletePlaceholder())->toBe(1);
                LegacyModel::paidBackToSent($db, $invoiceId);
                LegacyModel::deleteOrphans($db);
            }

            $state = ppayState($app, $db, $invoiceId);
            // Which flash is left over depends on the order of the two deletes, not on what they did.
            unset($state['view']['flash']);
            $steps["after $paymentId"] = $state;
        }

        $steps['custom rows'] = $db->table('ip_payment_custom')->count();
        $steps['unknown'] = $app->post('payments/delete/9999')->status();

        return $steps;
    });

    expect($answers['legacy'][2])->toBe([303, 'payments', ['invalid_request']])
        ->and($answers['new'][2])->toBe([303, 'payments', ['Record successfully deleted']]);
    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        // Paid (4) goes back to sent (2) and stays read-only; a viewed invoice (3) stays viewed.
        ->and($result->legacy['before']['stored']['invoices'][0])->toMatchArray(['invoice_status_id' => 4, 'is_read_only' => 1])
        ->and($result->legacy['after 2']['stored']['invoices'][0])->toMatchArray(['invoice_status_id' => 2, 'is_read_only' => 1, 'invoice_paid' => '500.00', 'invoice_balance' => '1213.80'])
        ->and($result->legacy['after 3']['stored']['invoices'][1])->toMatchArray(['invoice_status_id' => 3, 'invoice_paid' => '20.00', 'invoice_balance' => '80.00'])
        ->and($result->legacy['custom rows'])->toBe(0);
});

it('reads the amounts under european separators: price 1.234,56 and payments 500,5 and 734,06', function () {
    $result = parity(function (AppClient $app, string $side) {
        $db = ParityDatabases::connection($side);
        $app->login();
        $id = iparCreate($app)['id'];
        iparSave($app, $id, ['invoice_number' => '1', 'invoice_status_id' => '2', 'items' => iparItems($id, [['item_name' => 'Retainer', 'item_quantity' => '1', 'item_price' => '1.234,56']])]);
        $steps = ['modal' => ppayModal($app, $db, $id), 'form' => ppayForm($app)];

        // Review Focus 1.
        $steps['first'] = ppayAdd($app, $id, '500,5');
        $steps['after first'] = ppayState($app, $db, $id);
        $steps['too much'] = ppayAdd($app, $id, '1.234,56');
        $steps['edit form'] = ppayForm($app, 'payments/form/1');
        $steps['modal after'] = ppayModal($app, $db, $id);
        $steps['second'] = ppayAdd($app, $id, '734,06');
        $steps['after second'] = ppayState($app, $db, $id);
        $steps['edit form 2'] = ppayForm($app, 'payments/form/2');

        return $steps;
    }, ['decimal_point' => ',', 'thousands_separator' => '.']);

    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy['after first']['stored']['payments'][0])->toMatchArray(['payment_amount' => '500.50'])
        ->and($result->legacy['after first']['stored']['invoices'][0])->toMatchArray(['invoice_total' => '1234.56', 'invoice_balance' => '734.06'])
        ->and($result->legacy['after first']['payments']['rows'][1][4])->toBe('$500,50')
        ->and($result->legacy['after second']['stored']['invoices'][0])->toMatchArray(['invoice_status_id' => 4, 'is_read_only' => 1]);
});

it('fills the modal and the form with the invoice default payment method, locking it as legacy does', function () {
    $result = parity(function (AppClient $app, string $side) {
        $db = ParityDatabases::connection($side);
        $app->login();
        // The create modal posts invoice_default_payment_method as the invoice's method (modal_create_invoice.php:53).
        $withMethod = iparCreate($app, ['payment_method' => '2'])['id'];
        $without = iparCreate($app)['id'];

        foreach ([$withMethod, $without] as $invoice) {
            iparSave($app, $invoice, ['invoice_number' => (string) $invoice, 'invoice_status_id' => '2', 'payment_method' => (string) $db->table('ip_invoices')->where('invoice_id', $invoice)->value('payment_method'),
                'items' => iparItems($invoice, [['item_name' => 'Work', 'item_quantity' => '2', 'item_price' => '45.25']])]);
        }

        $app->get('invoices/ajax/modal_create_invoice', ajax: true);
        $steps = [
            'create modal' => $app->texts('//input[@id="payment_method_id"]/@value'),
            'with method' => ppayModal($app, $db, $withMethod),
            'without method' => ppayModal($app, $db, $without),
            'form' => ppayForm($app),
        ];

        // The form posts its method as a hidden field once an invoice with one is chosen (form.php:13-19).
        $steps['add on form'] = $app->post('payments/form', ['btn_submit' => '1', 'invoice_id' => (string) $withMethod, 'payment_date' => '10/15/2026', 'payment_amount' => '90.50', 'payment_method_id' => '2', 'payment_note' => 'form'])->status();
        $steps['form location'] = $app->location();
        $steps['edit form'] = ppayForm($app, 'payments/form/1');
        $steps['state'] = ppayState($app, $db, $withMethod);

        return $steps;
    }, ['invoice_default_payment_method' => '1']);

    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy['create modal'])->toBe(['1'])
        ->and($result->legacy['with method']['method'])->toMatchArray(['disabled' => ['disabled'], 'selected' => ['2']])
        ->and($result->legacy['without method']['method'])->toMatchArray(['disabled' => [], 'selected' => []]);
});

it('saves and edits payments on the form, shows its errors, and cancels', function () {
    $result = parity(function (AppClient $app, string $side) {
        $db = ParityDatabases::connection($side);
        $app->login();
        $id = ppayInvoice($app, $db);
        $other = ppayInvoice($app, $db);
        $form = fn (array $post, string $path = 'payments/form') => (function () use ($app, $post, $path) {
            $response = $app->post($path, $post);

            return $response->status() === 200 ? ppayFormPage($app, $response->body()) : ['status' => $response->status(), 'location' => $app->location()];
        })();
        $post = ['btn_submit' => '1', 'invoice_id' => (string) $id, 'payment_date' => '10/15/2026', 'payment_amount' => '700', 'payment_method_id' => '', 'payment_note' => 'n'];

        $steps = [
            'new' => $form($post),
            'list' => ppayList($app),
            'too high' => $form(['payment_amount' => '1500'] + $post),
            'no amount' => $form(['payment_amount' => ''] + $post),
            'no date' => $form(['payment_date' => ''] + $post),
            'cancel' => $form(['btn_cancel' => '1']),
            // The 700 already paid is added back to the balance: 1013.80 + 700 = 1713.80.
            'edit too high' => $form(['payment_id' => '1', 'payment_amount' => '1713.81'] + $post, 'payments/form/1'),
            'edit' => $form(['payment_id' => '1', 'payment_amount' => '1713.80', 'payment_note' => 'edited', 'payment_date' => '10/16/2026', 'payment_method_id' => '2'] + $post, 'payments/form/1'),
            'after edit' => ppayState($app, $db, $id),
            'edit list' => ppayList($app),
            'unknown form' => ppayForm($app, 'payments/form/9999'),
            'edit unknown' => $form(['payment_id' => '9999', 'payment_amount' => '1'] + $post, 'payments/form/9999'),
            // Payments.php:143-145 and Mdl_payments.php:104-108 read a payment that is not there: the form shows empty fields.
            'edit unknown ok' => $form(['payment_id' => '9999', 'invoice_id' => (string) $other, 'payment_amount' => '10'] + $post, 'payments/form/9999'),
            'edit unknown state' => ppayState($app, $db, $other),
            // CI stops a field at its first failing rule (Form_validation.php, _execute()'s `return`): one error for the amount, not two.
            'empty amount, unknown invoice' => $form(['payment_amount' => '', 'invoice_id' => '9999'] + $post),
            'empty amount, negative balance' => (function () use ($app, $db, $form, $post) {
                $negative = iparCreate($app)['id'];
                $rows = [['item_name' => 'Work', 'item_quantity' => '1', 'item_price' => '100.00']];
                iparSave($app, $negative, ['invoice_number' => (string) $negative, 'invoice_status_id' => '2', 'items' => iparItems($negative, $rows)]);
                ppayAdd($app, $negative, '100.00');
                // The price drops under what was paid: the balance is -50.00, and an empty amount (0) is above it.
                $rows[0]['item_price'] = '50.00';
                iparSave($app, $negative, ['invoice_number' => (string) $negative, 'invoice_status_id' => '2', 'items' => iparItems($negative, iparSavedItems($db, $negative, $rows))]);

                return [ppayStored($db)['invoices'][2], $form(['payment_amount' => '', 'invoice_id' => (string) $negative] + $post)];
            })(),
        ];

        return $steps;
    });

    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy['new'])->toBe(['status' => 303, 'location' => 'payments'])
        ->and($result->legacy['too high']['flash'])->not->toBe([])
        ->and($result->legacy['edit']['location'])->toBe('payments')
        ->and($result->legacy['after edit']['stored']['invoices'][0])->toMatchArray(['invoice_status_id' => 4, 'is_read_only' => 1, 'payment_method' => 2])
        ->and($result->legacy['unknown form']['status'])->toBe(404)
        ->and($result->legacy['empty amount, negative balance'][0])->toMatchArray(['invoice_balance' => '-50.00'])
        // One error, from the amount's callback ("cannot exceed"), not that and "required" too.
        ->and(array_filter($result->legacy['empty amount, negative balance'][1]['flash'], fn (string $text) => str_contains($text, 'Payment amount cannot exceed')))->toHaveCount(1)
        ->and(array_filter($result->legacy['empty amount, negative balance'][1]['flash'], fn (string $text) => str_contains($text, 'field is required')))->toBeEmpty();
});

it('lists payments in pages of default_list_limit, newest first, with the same pager and links', function (array $settings) {
    $result = parity(function (AppClient $app, string $side) {
        $db = ParityDatabases::connection($side);
        $app->login();
        $first = ppayInvoice($app, $db, ['client_id' => '1']);
        $second = ppayInvoice($app, $db, ['client_id' => '2']);

        foreach ([[$first, '100', '10/01/2026'], [$second, '200', '10/03/2026'], [$first, '300', '10/03/2026'], [$second, '400', '09/30/2026'], [$first, '50.5', '10/09/2026']] as [$invoice, $amount, $date]) {
            ppayAdd($app, $invoice, $amount, ['payment_date' => $date, 'payment_method_id' => $amount === '300' ? '2' : '']);
        }

        return [
            'index' => ppayList($app, 'payments'), 'page 0' => ppayList($app, 'payments/index/0'), 'page 2' => ppayList($app, 'payments/index/2'),
            'last' => ppayList($app, 'payments/index/4'), 'beyond' => ppayList($app, 'payments/index/99'),
        ];
    }, $settings);

    expect($result)->toHaveNoDifferences()->toMatchAcrossApps()
        ->and($result->legacy['page 0']['rows'])->not->toBeEmpty();
})->with([
    'default settings' => [[]],
    'default_list_limit = 2' => [['default_list_limit' => '2']],
]);

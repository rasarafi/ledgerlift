<?php

use App\Support\LegacyCrypt;
use Illuminate\Support\Facades\DB;

/*
 * The invoices module: its screens, the invoices/ajax/* endpoints and InvoiceService, against the
 * baseline database (group 3 "{{{id}}}", next id 1; date format m/d/Y; admin user 1). Tax rates:
 * 1 VAT 15.00, 2 Reduced 5.00, 3 Service 7.50.
 */

const INV_AJAX = ['X-Requested-With' => 'XMLHttpRequest'];

function invLogin(object $test): void
{
    $test->post('/sessions/login', ['email' => 'admin@ledgerlift.test', 'password' => 'ledgerlift-demo-1', 'btn_login' => 'true']);
}

function invSetup(): int
{
    DB::table('ip_tax_rates')->insert([
        ['tax_rate_id' => 1, 'tax_rate_name' => 'VAT', 'tax_rate_percent' => '15.00'],
        ['tax_rate_id' => 2, 'tax_rate_name' => 'Reduced', 'tax_rate_percent' => '5.00'],
        ['tax_rate_id' => 3, 'tax_rate_name' => 'Service', 'tax_rate_percent' => '7.50'],
    ]);

    return DB::table('ip_clients')->insertGetId([
        'client_name' => 'Ashgrove Joinery', 'client_active' => 1, 'client_date_created' => '2026-01-05 09:00:00', 'client_date_modified' => '2026-01-05 09:00:00',
    ]);
}

function invSetting(string $key, string $value): void
{
    DB::table('ip_settings')->where('setting_key', $key)->delete();
    DB::table('ip_settings')->insert(['setting_key' => $key, 'setting_value' => $value]);
}

/** @return array<string, mixed> The decoded answer of invoices/ajax/create. */
function invCreate(object $test, int $clientId, array $post = []): array
{
    $response = $test->continueSession()->post('/invoices/ajax/create', $post + [
        'client_id' => (string) $clientId, 'invoice_date_created' => '10/01/2026', 'invoice_group_id' => '3', 'invoice_time_created' => '09:15:00',
        'invoice_password' => '', 'user_id' => '1', 'payment_method' => '0',
    ], INV_AJAX)->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');

    return json_decode($response->getContent(), true);
}

/**
 * The items JSON the Save button builds (views/invoices/view.php:73-88): each row's inputs in
 * DOM order, then item_order.
 *
 * @param  list<array<string, string>>  $rows
 */
function invItems(int $invoiceId, array $rows): string
{
    $items = [];

    foreach ($rows as $order => $row) {
        $items[] = $row + [
            'invoice_id' => (string) $invoiceId, 'item_id' => '', 'item_product_id' => '', 'item_task_id' => '', 'item_name' => '',
            'item_quantity' => '', 'item_price' => '', 'item_tax_rate_id' => '0', 'item_discount_amount' => '', 'item_description' => '',
            'item_product_unit_id' => '0', 'item_order' => $order + 1,
        ];
    }

    return (string) json_encode($items);
}

/** @return string The raw body of invoices/ajax/save. */
function invSave(object $test, int $invoiceId, array $post = []): string
{
    return $test->continueSession()->post('/invoices/ajax/save', $post + [
        'legacy_calculation' => '1', 'invoice_id' => (string) $invoiceId, 'invoice_number' => (string) DB::table('ip_invoices')->where('invoice_id', $invoiceId)->value('invoice_number'),
        'invoice_date_created' => '10/01/2026', 'invoice_date_due' => '10/31/2026', 'invoice_status_id' => '1', 'invoice_password' => '',
        'items' => '[]', 'invoice_discount_amount' => '', 'invoice_discount_percent' => '', 'invoice_terms' => '', 'payment_method' => '0',
    ], INV_AJAX)->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8')->getContent();
}

/** @return array<string, mixed> */
function invAmounts(int $invoiceId): array
{
    return (array) DB::table('ip_invoice_amounts')->where('invoice_id', $invoiceId)
        ->first(['invoice_item_subtotal', 'invoice_item_tax_total', 'invoice_tax_total', 'invoice_total', 'invoice_paid', 'invoice_balance']);
}

it('sends a guest to the login page from every invoice screen', function () {
    foreach (['/invoices', '/invoices/index', '/invoices/status/all', '/invoices/view/1'] as $path) {
        $this->get($path)->assertRedirect(url('sessions/login'));
    }
    $this->post('/invoices/delete/1')->assertRedirect(url('sessions/login'));
    $this->post('/invoices/delete_invoice_tax/1/1')->assertRedirect(url('sessions/login'));
    $this->post('/invoices/ajax/save', [], INV_AJAX)->assertRedirect(url('sessions/login'));
});

describe('ajax', function () {
    beforeEach(function () {
        invLogin($this);
        $this->clientId = invSetup();
    });

    it('answers every AJAX endpoint with an empty 200 to a plain request', function () {
        foreach (['/invoices/ajax/modal_create_invoice', '/invoices/ajax/create', '/invoices/ajax/save', '/invoices/ajax/save_invoice_tax_rate', '/invoices/ajax/delete_item/1', '/filter/ajax/filter_invoices'] as $path) {
            $this->continueSession()->post($path, ['invoice_id' => '1'])->assertOk()->assertContent('');
        }
    });

    it('takes deletes over POST only', function () {
        $this->continueSession()->get('/invoices/delete/1')->assertNotFound();
        $this->continueSession()->get('/invoices/delete_invoice_tax/1/1')->assertNotFound();
        $this->continueSession()->get('/invoices/ajax/delete_item/1', INV_AJAX)->assertNotFound();
    });

    it('opens the create modal with the groups, the default one selected, and the posted client', function () {
        DB::table('ip_invoice_groups')->insert(['invoice_group_id' => 5, 'invoice_group_name' => 'Invoice INV', 'invoice_group_identifier_format' => 'INV-{{{id}}}', 'invoice_group_next_id' => 1, 'invoice_group_left_pad' => 0]);

        $this->continueSession()->get('/invoices/ajax/modal_create_invoice', INV_AJAX)->assertOk()
            ->assertSee('id="create-invoice"', false)
            ->assertSee('<option value="3" selected="selected">', false)
            ->assertSee('Invoice INV')
            ->assertSee('value="'.date('m/d/Y').'"', false)
            ->assertSee(url('invoices/ajax/create'), false)
            ->assertSee(url('clients/ajax/name_query'), false);

        $this->continueSession()->post('/invoices/ajax/modal_create_invoice', ['client_id' => (string) $this->clientId], INV_AJAX)
            ->assertSee('<option value="'.$this->clientId.'">Ashgrove Joinery</option>', false);
    });

    it('creates a draft with its amounts row, a number, the due date and a url key', function () {
        $answer = invCreate($this, $this->clientId);

        $invoice = (array) DB::table('ip_invoices')->where('invoice_id', $answer['invoice_id'])->first();
        expect($answer)->toBe(['success' => 1, 'invoice_id' => $invoice['invoice_id']])
            ->and($invoice)->toMatchArray([
                'client_id' => $this->clientId, 'user_id' => 1, 'invoice_group_id' => 3, 'invoice_status_id' => 1, 'invoice_number' => '1',
                'invoice_date_created' => '2026-10-01', 'invoice_time_created' => '09:15:00', 'invoice_date_due' => '2026-10-31',
                'invoice_password' => null, 'invoice_terms' => '', 'payment_method' => 0, 'is_read_only' => null,
            ])
            ->and($invoice['invoice_url_key'])->toMatch('/^[0-9a-f]{32}$/')
            ->and(strtotime($invoice['invoice_date_modified']))->toBeGreaterThan(time() - 60)
            ->and(DB::table('ip_invoice_amounts')->where('invoice_id', $invoice['invoice_id'])->count())->toBe(1)
            ->and(DB::table('ip_invoice_tax_rates')->count())->toBe(0)
            ->and(DB::table('ip_invoice_groups')->where('invoice_group_id', 3)->value('invoice_group_next_id'))->toBe(2);

        $this->continueSession()->get('/invoices/view/'.$invoice['invoice_id'])->assertOk()->assertSee('Record successfully created');
    });

    it('leaves a draft without a number when generate_invoice_number_for_draft is 0, and adds the default invoice tax', function () {
        invSetting('generate_invoice_number_for_draft', '0');
        invSetting('default_invoice_tax_rate', '3');
        invSetting('default_include_item_tax', '1');
        invSetting('default_invoice_terms', 'Net 30');

        $id = invCreate($this, $this->clientId)['invoice_id'];

        expect(DB::table('ip_invoices')->where('invoice_id', $id)->first(['invoice_number', 'invoice_terms']))->toEqual((object) ['invoice_number' => '', 'invoice_terms' => 'Net 30'])
            ->and((array) DB::table('ip_invoice_tax_rates')->first(['invoice_id', 'tax_rate_id', 'include_item_tax', 'invoice_tax_rate_amount']))
            ->toBe(['invoice_id' => $id, 'tax_rate_id' => 3, 'include_item_tax' => 1, 'invoice_tax_rate_amount' => '0.00'])
            ->and(DB::table('ip_invoice_groups')->where('invoice_group_id', 3)->value('invoice_group_next_id'))->toBe(1);
    });

    it('stores a PDF password encrypted, and the view shows it decrypted', function () {
        $id = invCreate($this, $this->clientId, ['invoice_password' => 'pdf-pass'])['invoice_id'];

        $stored = DB::table('ip_invoices')->where('invoice_id', $id)->value('invoice_password');
        expect($stored)->not->toBe('pdf-pass')->and(app(LegacyCrypt::class)->decode($stored))->toBe('pdf-pass');

        $this->continueSession()->get("/invoices/view/$id")->assertSee('value="pdf-pass"', false);
    });

    it('answers a create without the required fields with the errors of the posted fields', function () {
        $body = $this->continueSession()->post('/invoices/ajax/create', ['invoice_group_id' => '', 'client_id' => '', 'user_id' => '1'], INV_AJAX)->getContent();

        expect($body)->toBe('{"success":0,"validation_errors":{"invoice_group_id":"<p>The Invoice Group field is required.<\/p>","client_id":"<p>The Client field is required.<\/p>"}}')
            ->and(DB::table('ip_invoices')->count())->toBe(0);
    });

    it('saves items, the invoice and its discounts, then recalculates (the 10b invoice)', function () {
        $id = invCreate($this, $this->clientId)['invoice_id'];

        $body = invSave($this, $id, ['invoice_status_id' => '2', 'invoice_discount_percent' => '10', 'items' => invItems($id, [
            ['item_name' => 'Workshop fit-out', 'item_quantity' => '1', 'item_price' => '1200.00', 'item_tax_rate_id' => '1'],
            ['item_name' => 'Hinges', 'item_quantity' => '12', 'item_price' => '19.99', 'item_tax_rate_id' => '2', 'item_discount_amount' => '1.50'],
            ['item_name' => 'Labour', 'item_quantity' => '3.5', 'item_price' => '45.00'],
        ])]);
        $this->continueSession()->post('/invoices/ajax/save_invoice_tax_rate', ['invoice_id' => (string) $id, 'tax_rate_id' => '3', 'include_item_tax' => '1'], INV_AJAX)
            ->assertContent('{"success":1}');

        expect($body)->toBe('{"success":1}')
            ->and(DB::table('ip_invoice_items')->orderBy('item_order')->get(['item_name', 'item_quantity', 'item_price', 'item_discount_amount', 'item_product_id', 'item_product_unit_id', 'item_product_unit', 'item_date_added'])->map(fn ($row) => (array) $row)->all())
            ->toBe([
                ['item_name' => 'Workshop fit-out', 'item_quantity' => '1.00000000', 'item_price' => '1200.00', 'item_discount_amount' => null, 'item_product_id' => null, 'item_product_unit_id' => null, 'item_product_unit' => null, 'item_date_added' => date('Y-m-d')],
                ['item_name' => 'Hinges', 'item_quantity' => '12.00000000', 'item_price' => '19.99', 'item_discount_amount' => '1.50', 'item_product_id' => null, 'item_product_unit_id' => null, 'item_product_unit' => null, 'item_date_added' => date('Y-m-d')],
                ['item_name' => 'Labour', 'item_quantity' => '3.50000000', 'item_price' => '45.00', 'item_discount_amount' => null, 'item_product_id' => null, 'item_product_unit_id' => null, 'item_product_unit' => null, 'item_date_added' => date('Y-m-d')],
            ])
            ->and(invAmounts($id))->toBe([
                'invoice_item_subtotal' => '1579.38', 'invoice_item_tax_total' => '191.99', 'invoice_tax_total' => '132.85',
                'invoice_total' => '1713.80', 'invoice_paid' => '0.00', 'invoice_balance' => '1713.80',
            ])
            ->and(DB::table('ip_invoices')->where('invoice_id', $id)->first(['invoice_status_id', 'invoice_discount_percent', 'invoice_discount_amount']))
            ->toEqual((object) ['invoice_status_id' => 2, 'invoice_discount_percent' => '10.00', 'invoice_discount_amount' => '0.00']);

        $this->continueSession()->get("/invoices/view/$id")->assertOk()->assertSee('Record successfully created')
            ->assertSeeInOrder(['Subtotal', '$1,579.38', 'Item Tax', '$191.99', 'Invoice Tax', 'Service 7.50%', '$132.85', 'Total', '$1,713.80', 'Paid', '$0.00', 'Balance', '$1,713.80']);
    });

    it('keeps the percent when an amount and a percent are both posted (Ajax.php:41-43)', function () {
        $id = invCreate($this, $this->clientId)['invoice_id'];
        $items = invItems($id, [['item_name' => 'Consulting', 'item_quantity' => '1', 'item_price' => '100.00']]);

        invSave($this, $id, ['items' => $items, 'invoice_discount_amount' => '5', 'invoice_discount_percent' => '2.5']);
        expect(DB::table('ip_invoices')->where('invoice_id', $id)->first(['invoice_discount_amount', 'invoice_discount_percent']))
            ->toEqual((object) ['invoice_discount_amount' => '0.00', 'invoice_discount_percent' => '2.50'])
            ->and(invAmounts($id)['invoice_total'])->toBe('97.50');

        invSave($this, $id, ['items' => '[]', 'invoice_discount_amount' => '5']);
        expect(invAmounts($id)['invoice_total'])->toBe('95.00');
    });

    it('makes a settled invoice with two items read-only within one save, through the per-item recalculation', function (int $items, ?int $readOnly) {
        $id = invCreate($this, $this->clientId)['invoice_id'];
        $rows = array_fill(0, $items, ['item_name' => 'Part', 'item_quantity' => '1', 'item_price' => (string) (100 / $items)]);
        invSave($this, $id, ['invoice_status_id' => '2', 'items' => invItems($id, $rows)]);
        DB::table('ip_payments')->insert(['invoice_id' => $id, 'payment_method_id' => 0, 'payment_date' => '2026-10-02', 'payment_amount' => '100.00', 'payment_note' => '']);
        $saved = DB::table('ip_invoice_items')->orderBy('item_order')->pluck('item_id')->map(fn (int $itemId, int $i) => $rows[$i] + ['item_id' => (string) $itemId])->all();

        invSave($this, $id, ['invoice_status_id' => '2', 'items' => invItems($id, $saved)]);

        // Item 1 settles it (2 → 4); item 2 sees 4 = read_only_toggle and sets read-only; the posted
        // status 2 overwrites the 4; the trailing recalculation sets 4 again, from 2.
        expect(DB::table('ip_invoices')->where('invoice_id', $id)->first(['invoice_status_id', 'is_read_only']))
            ->toEqual((object) ['invoice_status_id' => 4, 'is_read_only' => $readOnly]);
    })->with([
        'one item' => [1, null],
        'two items' => [2, 1],
    ]);

    it('generates a number for a non-draft saved without one, and marks it read-only when its status is read_only_toggle', function () {
        invSetting('generate_invoice_number_for_draft', '0');
        $id = invCreate($this, $this->clientId)['invoice_id'];

        invSave($this, $id, ['invoice_number' => '', 'invoice_status_id' => '1']);
        expect(DB::table('ip_invoices')->where('invoice_id', $id)->value('invoice_number'))->toBe('');

        invSave($this, $id, ['invoice_number' => '', 'invoice_status_id' => '2']);
        expect(DB::table('ip_invoices')->where('invoice_id', $id)->first(['invoice_number', 'is_read_only']))->toEqual((object) ['invoice_number' => '1', 'is_read_only' => null]);

        invSave($this, $id, ['invoice_status_id' => '4']);
        expect(DB::table('ip_invoices')->where('invoice_id', $id)->value('is_read_only'))->toBe(1);
    });

    it('refuses a duplicate number with the validation error JSON and changes nothing', function () {
        $first = invCreate($this, $this->clientId)['invoice_id'];
        $second = invCreate($this, $this->clientId)['invoice_id'];
        $before = (array) DB::table('ip_invoices')->where('invoice_id', $second)->first();

        $body = invSave($this, $second, ['invoice_number' => '1', 'invoice_terms' => 'changed']);

        expect($body)->toBe('{"success":0,"validation_errors":{"invoice_number":"<p>The Invoice # field must contain a unique value.<\/p>"}}')
            ->and((array) DB::table('ip_invoices')->where('invoice_id', $second)->first())->toBe($before)
            ->and(invSave($this, $first, ['invoice_number' => '1']))->toBe('{"success":1}');
    });

    it('requires both dates', function () {
        $id = invCreate($this, $this->clientId)['invoice_id'];

        expect(invSave($this, $id, ['invoice_date_due' => '', 'invoice_date_created' => ' ']))
            // json_errors(): in the order the fields were posted.
            ->toBe('{"success":0,"validation_errors":{"invoice_date_due":"<p>The Due Date field is required.<\/p>","invoice_date_created":"<p>The Date field is required.<\/p>"}}');
    });

    it('echoes the error of a number with invalid characters and still saves it, as legacy does', function () {
        $id = invCreate($this, $this->clientId)['invoice_id'];

        expect(invSave($this, $id, ['invoice_number' => 'A"B']))
            ->toBe('{"success":0,"validation_errors":{"invoice_number":"invoice_number contains invalid characters"}}{"success":1}')
            ->and(DB::table('ip_invoices')->where('invoice_id', $id)->value('invoice_number'))->toBe('A"B');
    });

    it('echoes the error of an item with a price but no name and saves the rest', function () {
        $id = invCreate($this, $this->clientId)['invoice_id'];

        $body = invSave($this, $id, ['items' => invItems($id, [['item_price' => '5'], ['item_name' => 'Named', 'item_quantity' => '2', 'item_price' => '5']])]);

        expect($body)->toBe('{"success":0,"validation_errors":{"item_name":"<p>The Item field is required.<\/p>"}}{"success":1}')
            ->and(DB::table('ip_invoice_items')->pluck('item_name')->all())->toBe(['Named'])
            ->and(invAmounts($id)['invoice_total'])->toBe('10.00');
    });

    it('updates an existing item and names its unit by quantity', function () {
        DB::table('ip_units')->insert(['unit_id' => 1, 'unit_name' => 'hour', 'unit_name_plrl' => 'hours']);
        $id = invCreate($this, $this->clientId)['invoice_id'];
        invSave($this, $id, ['items' => invItems($id, [['item_name' => 'Dev', 'item_quantity' => '1', 'item_price' => '95', 'item_product_unit_id' => '1']])]);
        $itemId = DB::table('ip_invoice_items')->value('item_id');
        expect(DB::table('ip_invoice_items')->value('item_product_unit'))->toBe('hour');

        invSave($this, $id, ['items' => invItems($id, [['item_id' => (string) $itemId, 'item_name' => 'Dev', 'item_quantity' => '2,5', 'item_price' => '95', 'item_product_unit_id' => '1']])]);

        expect(DB::table('ip_invoice_items')->count())->toBe(1)
            ->and(DB::table('ip_invoice_items')->first(['item_quantity', 'item_product_unit']))->toEqual((object) ['item_quantity' => '25.00000000', 'item_product_unit' => 'hours'])
            ->and(DB::table('ip_invoice_item_amounts')->where('item_id', $itemId)->value('item_total'))->toBe('2375.00');
    });

    it('answers an invoice tax without its fields with the error string', function () {
        $body = $this->continueSession()->post('/invoices/ajax/save_invoice_tax_rate', ['invoice_id' => '1', 'tax_rate_id' => ''], INV_AJAX)->getContent();

        expect($body)->toBe('{"success":0,"validation_errors":"<p>The Tax Rate field is required.<\/p>\n<p>The Tax Rate Placement field is required.<\/p>\n"}')
            ->and(DB::table('ip_invoice_tax_rates')->count())->toBe(0);
    });

    it('deletes an item of an existing invoice and recalculates', function () {
        $id = invCreate($this, $this->clientId)['invoice_id'];
        invSave($this, $id, ['items' => invItems($id, [['item_name' => 'A', 'item_quantity' => '1', 'item_price' => '10'], ['item_name' => 'B', 'item_quantity' => '1', 'item_price' => '5']])]);
        $itemId = DB::table('ip_invoice_items')->where('item_name', 'B')->value('item_id');

        $delete = fn (string $invoice, array $post) => $this->continueSession()->post("/invoices/ajax/delete_item/$invoice", $post, INV_AJAX)->assertHeader('Content-Type', 'text/html; charset=UTF-8')->getContent();

        expect($delete('9999', ['item_id' => (string) $itemId]))->toBe('{"success":0}')
            ->and($delete((string) $id, ['item_id' => '9999']))->toBe('{"success":0}')
            ->and($delete((string) $id, []))->toBe('{"success":0}')
            ->and($delete((string) $id, ['item_id' => (string) $itemId]))->toBe('{"success":1}')
            ->and(DB::table('ip_invoice_item_amounts')->where('item_id', $itemId)->count())->toBe(0)
            ->and(invAmounts($id)['invoice_total'])->toBe('10.00');

        $this->continueSession()->get("/invoices/view/$id")->assertSee('Record successfully deleted');
    });

    it('filters the invoice list over AJAX', function () {
        $first = invCreate($this, $this->clientId)['invoice_id'];
        $second = invCreate($this, $this->clientId)['invoice_id'];
        DB::table('ip_invoices')->where('invoice_id', $second)->update(['invoice_number' => 'XYZ-9']);

        $this->continueSession()->post('/filter/ajax/filter_invoices', ['filter_query' => 'xyz'], INV_AJAX)->assertOk()
            ->assertSee(url("invoices/view/$second"), false)->assertDontSee(url("invoices/view/$first"), false);
    });
});

describe('screens', function () {
    beforeEach(function () {
        invLogin($this);
        $this->clientId = invSetup();
    });

    it('redirects invoices and invoices/index to the full list', function () {
        $this->continueSession()->get('/invoices')->assertStatus(307)->assertRedirect(url('invoices/status/all'));
        $this->continueSession()->get('/invoices/index')->assertStatus(307)->assertRedirect(url('invoices/status/all'));
    });

    it('lists the invoices of each status', function () {
        $draft = invCreate($this, $this->clientId)['invoice_id'];
        $sent = invCreate($this, $this->clientId)['invoice_id'];
        $paid = invCreate($this, $this->clientId)['invoice_id'];
        $settled = invCreate($this, $this->clientId)['invoice_id'];
        $overdue = invCreate($this, $this->clientId)['invoice_id'];
        DB::table('ip_invoices')->where('invoice_id', $sent)->update(['invoice_status_id' => 2, 'invoice_date_due' => '2099-01-01']);
        DB::table('ip_invoices')->where('invoice_id', $paid)->update(['invoice_status_id' => 4]);
        DB::table('ip_invoices')->where('invoice_id', $settled)->update(['invoice_status_id' => 3, 'invoice_date_due' => '2099-01-01']);
        DB::table('ip_invoice_amounts')->where('invoice_id', $settled)->update(['invoice_balance' => '0.00']);
        DB::table('ip_invoices')->where('invoice_id', $overdue)->update(['invoice_status_id' => 2, 'invoice_date_due' => '2020-01-01']);

        $ids = function (string $status) {
            preg_match_all('#'.preg_quote(url('invoices/view'), '#').'/(\d+)"\s+title#', $this->continueSession()->get("/invoices/status/$status")->assertOk()->getContent(), $matches);

            return array_map('intval', $matches[1]);
        };

        expect($ids('all'))->toEqualCanonicalizing([$draft, $sent, $paid, $settled, $overdue])
            ->and($ids('draft'))->toBe([$draft])
            ->and($ids('sent'))->toEqualCanonicalizing([$sent, $overdue])
            ->and($ids('viewed'))->toBe([$settled])
            ->and($ids('paid'))->toEqualCanonicalizing([$paid, $settled])
            ->and($ids('overdue'))->toBe([$overdue]);

        $this->continueSession()->get('/invoices/status/overdue')->assertSee('class="btn btn-primary"', false)
            ->assertSee('placeholder="Filter Invoices"', false)->assertSee(url('filter/ajax/filter_invoices'), false);
    });

    it('shows the invoice, 404s a missing one, and routes the PDF to the legacy app', function () {
        $id = invCreate($this, $this->clientId)['invoice_id'];

        $this->continueSession()->get("/invoices/view/$id")->assertOk()
            ->assertSee('Invoice #1')
            ->assertSee('Ashgrove Joinery')
            ->assertSee(url('invoices/ajax/save'), false)
            ->assertSee('action="'.url("invoices/delete/$id").'"', false)
            ->assertSee(rtrim((string) config('ledgerlift.legacy_url'), '/')."/invoices/generate_pdf/$id", false);
        $this->continueSession()->get('/invoices/view/9999')->assertNotFound();
    });

    it('deletes a draft and its orphans, and refuses a sent invoice', function () {
        $draft = invCreate($this, $this->clientId)['invoice_id'];
        $sent = invCreate($this, $this->clientId)['invoice_id'];
        invSave($this, $draft, ['items' => invItems($draft, [['item_name' => 'A', 'item_quantity' => '1', 'item_price' => '10']])]);
        DB::table('ip_invoices')->where('invoice_id', $sent)->update(['invoice_status_id' => 2]);

        // invoices/index renders no alerts, and the flash does not outlive its two redirects (as on legacy).
        $this->continueSession()->post("/invoices/delete/$draft")->assertStatus(303)->assertRedirect(url('invoices/index'))
            ->assertSessionHas('alert_success', 'Record successfully deleted');
        expect(DB::table('ip_invoices')->pluck('invoice_id')->all())->toBe([$sent])
            ->and(DB::table('ip_invoice_items')->count())->toBe(0)
            ->and(DB::table('ip_invoice_item_amounts')->count())->toBe(0)
            ->and(DB::table('ip_invoice_amounts')->pluck('invoice_id')->all())->toBe([$sent]);

        $this->continueSession()->post("/invoices/delete/$sent")->assertStatus(303)->assertRedirect(url('invoices/index'))
            ->assertSessionHas('alert_error', __('ip.invoice_deletion_forbidden'));
        expect(DB::table('ip_invoices')->count())->toBe(1);

        $this->continueSession()->post('/invoices/delete/9999')->assertNotFound();
    });

    it('deletes a sent invoice when ENABLE_INVOICE_DELETION is true', function () {
        config(['ledgerlift.enable_invoice_deletion' => true]);
        $sent = invCreate($this, $this->clientId)['invoice_id'];
        DB::table('ip_invoices')->where('invoice_id', $sent)->update(['invoice_status_id' => 2]);

        $this->continueSession()->post("/invoices/delete/$sent")->assertStatus(303);

        expect(DB::table('ip_invoices')->count())->toBe(0);
    });

    it('deletes an invoice tax and recalculates', function () {
        $id = invCreate($this, $this->clientId)['invoice_id'];
        invSave($this, $id, ['items' => invItems($id, [['item_name' => 'A', 'item_quantity' => '1', 'item_price' => '100']])]);
        $this->continueSession()->post('/invoices/ajax/save_invoice_tax_rate', ['invoice_id' => (string) $id, 'tax_rate_id' => '1', 'include_item_tax' => '0'], INV_AJAX);
        expect(invAmounts($id)['invoice_total'])->toBe('115.00');
        $taxId = DB::table('ip_invoice_tax_rates')->value('invoice_tax_rate_id');

        $this->continueSession()->post("/invoices/delete_invoice_tax/$id/$taxId")->assertStatus(303)->assertRedirect(url("invoices/view/$id"));

        expect(DB::table('ip_invoice_tax_rates')->count())->toBe(0)
            ->and(invAmounts($id))->toMatchArray(['invoice_tax_total' => '0.00', 'invoice_total' => '100.00']);
        $this->continueSession()->get("/invoices/view/$id")->assertSee('Record successfully deleted');
    });
});

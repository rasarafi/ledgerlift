<?php

use Illuminate\Support\Facades\DB;

/*
 * The payments module: its screens, the payments/ajax/* endpoints and PaymentService, against the
 * baseline database (date format m/d/Y; admin user 1). Payment methods 1 Cash, 2 Credit Card.
 */

const PAY_AJAX = ['X-Requested-With' => 'XMLHttpRequest'];

function payLogin(object $test): void
{
    $test->post('/sessions/login', ['email' => 'admin@ledgerlift.test', 'password' => 'ledgerlift-demo-1', 'btn_login' => 'true']);
}

/**
 * A sent invoice with one item of `$price`, its amounts as a recalculation would leave them.
 *
 * @return int The invoice id.
 */
function paySeedInvoice(string $price = '100.00', int $status = 2, string $number = 'INV-1', ?int $clientId = null): int
{
    $clientId ??= DB::table('ip_clients')->value('client_id') ?: DB::table('ip_clients')->insertGetId([
        'client_name' => 'Ashgrove Joinery', 'client_active' => 1, 'client_date_created' => '2026-01-05 09:00:00', 'client_date_modified' => '2026-01-05 09:00:00',
    ]);

    $invoiceId = DB::table('ip_invoices')->insertGetId([
        'client_id' => $clientId, 'user_id' => 1, 'invoice_group_id' => 3, 'invoice_status_id' => $status, 'invoice_number' => $number,
        'invoice_date_created' => '2026-10-01', 'invoice_date_modified' => '2026-10-01 09:00:00', 'invoice_time_created' => '09:00:00',
        'invoice_date_due' => '2026-10-31', 'invoice_url_key' => bin2hex(random_bytes(16)),
    ]);
    $itemId = DB::table('ip_invoice_items')->insertGetId([
        'invoice_id' => $invoiceId, 'item_name' => 'Service', 'item_quantity' => '1', 'item_price' => $price, 'item_order' => 1,
    ]);
    DB::table('ip_invoice_item_amounts')->insert(['item_id' => $itemId, 'item_subtotal' => $price, 'item_tax_total' => '0.00', 'item_discount' => '0.00', 'item_total' => $price]);
    DB::table('ip_invoice_amounts')->insert([
        'invoice_id' => $invoiceId, 'invoice_item_subtotal' => $price, 'invoice_item_tax_total' => '0.00', 'invoice_tax_total' => '0.00',
        'invoice_total' => $price, 'invoice_paid' => '0.00', 'invoice_balance' => $price,
    ]);

    return $invoiceId;
}

function paySetting(string $key, string $value): void
{
    DB::table('ip_settings')->where('setting_key', $key)->delete();
    DB::table('ip_settings')->insert(['setting_key' => $key, 'setting_value' => $value]);
}

/** The modal's post (views/payments/modal_add_payment.php:191-197): the raw body. */
function payAdd(object $test, int $invoiceId, string $amount, array $post = []): string
{
    return $test->continueSession()->post('/payments/ajax/add', $post + [
        'invoice_id' => (string) $invoiceId, 'payment_amount' => $amount, 'payment_method_id' => '1', 'payment_date' => '10/15/2026', 'payment_note' => '',
    ], PAY_AJAX)->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8')->getContent();
}

/** @return array<string, mixed> */
function payInvoice(int $invoiceId): array
{
    return (array) DB::table('ip_invoices')->join('ip_invoice_amounts', 'ip_invoice_amounts.invoice_id', '=', 'ip_invoices.invoice_id')
        ->where('ip_invoices.invoice_id', $invoiceId)
        ->first(['invoice_status_id', 'is_read_only', 'payment_method', 'invoice_total', 'invoice_paid', 'invoice_balance']);
}

it('sends a guest to the login page from every payment screen', function () {
    foreach (['/payments', '/payments/index', '/payments/form', '/payments/form/1'] as $path) {
        $this->get($path)->assertRedirect(url('sessions/login'));
    }
    $this->post('/payments/delete/1')->assertRedirect(url('sessions/login'));
    $this->post('/payments/ajax/add', [], PAY_AJAX)->assertRedirect(url('sessions/login'));
});

describe('payments', function () {
    beforeEach(function () {
        payLogin($this);
        $this->invoiceId = paySeedInvoice();
    });

    it('answers every AJAX endpoint with an empty 200 to a plain request', function () {
        foreach (['/payments/ajax/modal_add_payment', '/payments/ajax/add', '/filter/ajax/filter_payments'] as $path) {
            $this->continueSession()->post($path, ['invoice_id' => '1'])->assertOk()->assertContent('');
        }
    });

    it('takes deletes over POST only, and numeric ids only', function () {
        $this->continueSession()->get('/payments/delete/1')->assertNotFound();
        $this->continueSession()->get('/payments/form/1-alert(1)')->assertNotFound();
        $this->continueSession()->get('/payments/index/1-alert(1)')->assertNotFound();
        $this->continueSession()->post('/payments/delete/1-alert(1)')->assertNotFound();
    });

    it('adds a payment: standardised amount, mysql date, recalculated invoice, text/html JSON', function () {
        $body = payAdd($this, $this->invoiceId, '40.50', ['payment_note' => 'First half']);

        expect($body)->toBe('{"success":1,"payment_id":1}')
            ->and((array) DB::table('ip_payments')->first())->toBe([
                'payment_id' => 1, 'invoice_id' => $this->invoiceId, 'payment_method_id' => 1, 'payment_date' => '2026-10-15', 'payment_amount' => '40.50', 'payment_note' => 'First half',
            ])
            ->and(payInvoice($this->invoiceId))->toBe([
                'invoice_status_id' => 2, 'is_read_only' => null, 'payment_method' => 0, 'invoice_total' => '100.00', 'invoice_paid' => '40.50', 'invoice_balance' => '59.50',
            ]);

        $this->continueSession()->get('/payments/index')->assertOk()->assertSee('Record successfully created');
    });

    it('pays an invoice off with the second payment: status paid, then read-only, method of the first payment', function () {
        payAdd($this, $this->invoiceId, '40.50', ['payment_method_id' => '2']);
        payAdd($this, $this->invoiceId, '59.50', ['payment_method_id' => '1']);

        // Mdl_payments::save() recalculates twice: the first pass sets status 4, the second sees it and sets read-only.
        expect(payInvoice($this->invoiceId))->toBe([
            'invoice_status_id' => 4, 'is_read_only' => 1, 'payment_method' => 2, 'invoice_total' => '100.00', 'invoice_paid' => '100.00', 'invoice_balance' => '0.00',
        ]);
    });

    it('rejects an overpayment with the balance message and stores nothing', function () {
        $body = payAdd($this, $this->invoiceId, '100.01');

        expect($body)->toBe('{"success":0,"validation_errors":{"payment_amount":"<p>Payment amount cannot exceed invoice balance.<\/p>"}}')
            ->and(DB::table('ip_payments')->count())->toBe(0)
            ->and(payInvoice($this->invoiceId)['invoice_balance'])->toBe('100.00');
    });

    it('accepts a zero and a negative payment, as legacy does (no amount > 0 check)', function () {
        expect(payAdd($this, $this->invoiceId, '0'))->toBe('{"success":1,"payment_id":1}')
            ->and(payAdd($this, $this->invoiceId, '-25'))->toBe('{"success":1,"payment_id":2}')
            ->and(DB::table('ip_payments')->orderBy('payment_id')->pluck('payment_amount')->all())->toBe(['0.00', '-25.00'])
            ->and(payInvoice($this->invoiceId))->toMatchArray(['invoice_paid' => '-25.00', 'invoice_balance' => '125.00', 'invoice_status_id' => 2]);
    });

    it('lets a zero payment settle an invoice whose total is zero (paid >= total, Mdl_payments.php:391)', function () {
        $zero = paySeedInvoice('0.00', 1, 'INV-0');

        payAdd($this, $zero, '0');

        expect(payInvoice($zero))->toMatchArray(['invoice_status_id' => 4, 'invoice_paid' => '0.00']);
    });

    it('answers missing fields with the errors of the posted fields, in post order', function () {
        $body = $this->continueSession()->post('/payments/ajax/add', ['payment_date' => '', 'invoice_id' => '', 'payment_amount' => ''], PAY_AJAX)->getContent();

        // CI runs the amount's callback before `required`, and it fails without a message of its own on an unknown invoice.
        expect($body)->toBe('{"success":0,"validation_errors":{"payment_date":"<p>The Date field is required.<\/p>","invoice_id":"<p>The Invoice field is required.<\/p>","payment_amount":"<p>Unable to access an error message corresponding to your field name Payment.(validate_payment_amount)<\/p>"}}')
            ->and(payAdd($this, $this->invoiceId, ''))->toBe('{"success":0,"validation_errors":{"payment_amount":"<p>The Payment field is required.<\/p>"}}')
            ->and(payAdd($this, 9999, '5'))->toContain('(validate_payment_amount)')
            ->and(DB::table('ip_payments')->count())->toBe(0);
    });

    it('reads the amount with the configured separators: 500,5 is 500.50', function () {
        paySetting('decimal_point', ',');
        paySetting('thousands_separator', '.');
        $invoice = paySeedInvoice('1234.56', 2, 'INV-E');

        expect(payAdd($this, $invoice, '500,5'))->toBe('{"success":1,"payment_id":1}')
            ->and(DB::table('ip_payments')->value('payment_amount'))->toBe('500.50')
            ->and(payInvoice($invoice)['invoice_balance'])->toBe('734.06')
            // 1.234,56 is more than the 734.06 balance.
            ->and(payAdd($this, $invoice, '1.234,56'))->toBe('{"success":0,"validation_errors":{"payment_amount":"<p>Payment amount cannot exceed invoice balance.<\/p>"}}');
    });

    it('opens the add-payment modal with the posted invoice, its balance, the date and the method', function () {
        $this->continueSession()->post('/payments/ajax/modal_add_payment', [
            'invoice_id' => (string) $this->invoiceId, 'invoice_balance' => '1234.5', 'invoice_payment_method' => '2', 'payment_cf_exist' => 'no',
        ], PAY_AJAX)->assertOk()
            ->assertSee('id="enter-payment"', false)
            ->assertSee('<input type="hidden" name="invoice_id" id="invoice_id" value="'.$this->invoiceId.'">', false)
            ->assertSee('value="1,234.50"', false)
            ->assertSee('value="'.date('m/d/Y').'"', false)
            ->assertSee('selected="selected"', false)
            ->assertSee('disabled="disabled"', false)
            ->assertSee('id="payment_cf_exist" value="no"', false)
            ->assertSee(url('payments/ajax/add'), false);

        // No method on the invoice: the select is enabled and nothing but "None" is selected.
        $this->continueSession()->post('/payments/ajax/modal_add_payment', ['invoice_id' => (string) $this->invoiceId], PAY_AJAX)
            ->assertOk()->assertDontSee('disabled="disabled"', false)->assertDontSee('selected="selected"', false);
    });

    it('sends the modal back to the page it was opened from, or the invoice list for a foreign or malformed referer', function (string $referer, string $expected) {
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $referer = str_replace('{app}', url('/'), $referer);
        $expected = str_replace('{app}', url('/'), $expected);

        $this->continueSession()->post('/payments/ajax/modal_add_payment', ['invoice_id' => '1'], PAY_AJAX + ['Referer' => $referer])
            ->assertSee('window.location = '.json_encode($expected, $flags).';', false);
    })->with([
        'same page' => ['{app}/invoices/view/1', '{app}/invoices/view/1'],
        'other host' => ['https://evil.example/x', '{app}/invoices'],
        'other port' => ['http://localhost:9/invoices/view/1', '{app}/invoices'],
        'other scheme' => ['https://localhost:8000/invoices/view/1', '{app}/invoices'],
        'script url' => ['javascript:alert(1)', '{app}/invoices'],
        'angle bracket' => ['{app}/invoices/view/1?x=<b>', '{app}/invoices'],
    ]);

    it('lists payments newest first, formats the amounts, and filters over AJAX', function () {
        $other = paySeedInvoice('2000.00', 2, 'INV-2');
        payAdd($this, $this->invoiceId, '10', ['payment_date' => '10/01/2026', 'payment_note' => 'older']);
        payAdd($this, $other, '1234.5', ['payment_date' => '10/05/2026', 'payment_method_id' => '2']);

        $this->continueSession()->get('/payments')->assertOk();
        $this->continueSession()->get('/payments/index')->assertOk()
            ->assertSee('Filter Payments')
            ->assertSeeInOrder(['10/05/2026', 'INV-2', 'Ashgrove Joinery', '$1,234.50', 'Credit Card', '10/01/2026', 'INV-1', '$10.00', 'older']);

        $this->continueSession()->post('/filter/ajax/filter_payments', ['filter_query' => 'older'], PAY_AJAX)
            ->assertOk()->assertSee('INV-1')->assertDontSee('INV-2')->assertDontSee('<html', false);
        $this->continueSession()->post('/filter/ajax/filter_payments', ['filter_query' => 'credit inv-2'], PAY_AJAX)
            ->assertSee('INV-2')->assertDontSee('INV-1');
    });

    it('opens a blank form with the open invoices only and today as the date', function () {
        paySeedInvoice('10.00', 1, 'DRAFT-1');
        paySeedInvoice('20.00', 3, 'VIEWED-1');
        $paid = paySeedInvoice('30.00', 2, 'SETTLED-1');
        DB::table('ip_invoice_amounts')->where('invoice_id', $paid)->update(['invoice_paid' => '30.00', 'invoice_balance' => '0.00']);

        $this->continueSession()->get('/payments/form')->assertOk()
            ->assertSee('Payment Form')
            ->assertSee('INV-1 - Ashgrove Joinery - $100.00')
            ->assertSee('VIEWED-1 - Ashgrove Joinery - $20.00')
            ->assertDontSee('DRAFT-1')->assertDontSee('SETTLED-1')
            ->assertSee('value="'.date('m/d/Y').'"', false)
            ->assertSee('"invoice'.$this->invoiceId.'":"100.00"', false)
            ->assertDontSee('name="payment_id"', false);
    });

    it('saves a new payment from the form and goes back to the list', function () {
        $this->continueSession()->post('/payments/form', [
            'btn_submit' => '1', 'invoice_id' => (string) $this->invoiceId, 'payment_date' => '10/15/2026', 'payment_amount' => '25', 'payment_method_id' => '1', 'payment_note' => 'form',
        ])->assertStatus(303)->assertRedirect(url('payments'));

        expect(DB::table('ip_payments')->first(['payment_amount', 'payment_date', 'payment_note']))->toEqual((object) ['payment_amount' => '25.00', 'payment_date' => '2026-10-15', 'payment_note' => 'form'])
            ->and(payInvoice($this->invoiceId)['invoice_balance'])->toBe('75.00');

        $this->continueSession()->get('/payments')->assertSee('Record successfully created');
    });

    it('shows the form again with the posted values and the error when the amount is too high', function () {
        $this->continueSession()->post('/payments/form', [
            'btn_submit' => '1', 'invoice_id' => (string) $this->invoiceId, 'payment_date' => '10/15/2026', 'payment_amount' => '500', 'payment_method_id' => '', 'payment_note' => 'keep me',
        ])->assertOk()->assertSee('Payment amount cannot exceed invoice balance.')->assertSee('keep me')->assertSee('value="500.00"', false);

        expect(DB::table('ip_payments')->count())->toBe(0);
    });

    it('cancels from the form', function () {
        $this->continueSession()->post('/payments/form', ['btn_cancel' => '1'])->assertStatus(303)->assertRedirect(url('payments'));
        expect(DB::table('ip_payments')->count())->toBe(0);
    });

    it('opens an existing payment with its invoice, a hidden id and the method locked', function () {
        payAdd($this, $this->invoiceId, '40', ['payment_method_id' => '2', 'payment_note' => 'note <b>']);

        $this->continueSession()->get('/payments/form/1')->assertOk()
            ->assertSee('<input type="hidden" name="payment_id" value="1">', false)
            ->assertSee('INV-1 - Ashgrove Joinery - $60.00')
            ->assertSee('value="10/15/2026"', false)
            ->assertSee('value="40.00"', false)
            ->assertSee('class="hidden"', false)
            ->assertSee('disabled="disabled"', false)
            ->assertSee('note &lt;b&gt;', false);

        $this->continueSession()->get('/payments/form/999')->assertNotFound();
    });

    it('updates a payment, adding the old amount back to the balance before validating', function () {
        payAdd($this, $this->invoiceId, '60');
        $edit = fn (string $amount) => $this->continueSession()->post('/payments/form/1', [
            'btn_submit' => '1', 'payment_id' => '1', 'invoice_id' => (string) $this->invoiceId, 'payment_date' => '10/16/2026', 'payment_amount' => $amount, 'payment_method_id' => '1', 'payment_note' => 'edited',
        ]);

        // Balance 40 + the old 60 = 100.
        $edit('100.01')->assertOk()->assertSee('Payment amount cannot exceed invoice balance.');
        expect(DB::table('ip_payments')->value('payment_amount'))->toBe('60.00');

        $edit('100')->assertStatus(303)->assertRedirect(url('payments'));
        expect(DB::table('ip_payments')->first(['payment_amount', 'payment_date', 'payment_note']))->toEqual((object) ['payment_amount' => '100.00', 'payment_date' => '2026-10-16', 'payment_note' => 'edited'])
            ->and(payInvoice($this->invoiceId))->toMatchArray(['invoice_status_id' => 4, 'is_read_only' => 1, 'invoice_balance' => '0.00']);

        $this->continueSession()->get('/payments')->assertSee('Record successfully updated');
    });

    it('deletes a payment: recalculates, paid goes back to sent, read-only stays', function () {
        payAdd($this, $this->invoiceId, '100');
        expect(payInvoice($this->invoiceId))->toMatchArray(['invoice_status_id' => 4, 'is_read_only' => 1]);
        DB::table('ip_payment_custom')->insert(['payment_id' => 1, 'payment_custom_fieldid' => 1, 'payment_custom_fieldvalue' => 'x']);

        $this->continueSession()->post('/payments/delete/1')->assertStatus(303)->assertRedirect(url('payments'));

        expect(DB::table('ip_payments')->count())->toBe(0)
            ->and(DB::table('ip_payment_custom')->count())->toBe(0)
            ->and(payInvoice($this->invoiceId))->toBe([
                'invoice_status_id' => 2, 'is_read_only' => 1, 'payment_method' => 1, 'invoice_total' => '100.00', 'invoice_paid' => '0.00', 'invoice_balance' => '100.00',
            ]);

        $this->continueSession()->get('/payments')->assertSee('Record successfully deleted');
    });

    it('leaves a viewed invoice viewed when one of its payments is deleted', function () {
        $viewed = paySeedInvoice('100.00', 3, 'INV-V');
        payAdd($this, $viewed, '10');

        $this->continueSession()->post('/payments/delete/1');

        expect(payInvoice($viewed)['invoice_status_id'])->toBe(3);
    });

    it('shows the add-payment link of the invoice view and list from the modal on this app', function () {
        $this->continueSession()->get('/invoices/view/'.$this->invoiceId)->assertOk()
            ->assertSee(url('payments/ajax/modal_add_payment'), false);
    });
});

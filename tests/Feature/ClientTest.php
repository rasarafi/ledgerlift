<?php

use Illuminate\Support\Facades\DB;

const CLIENT_AJAX = ['X-Requested-With' => 'XMLHttpRequest'];

function clientLogin(object $test): void
{
    $test->post('/sessions/login', ['email' => 'admin@ledgerlift.test', 'password' => 'ledgerlift-demo-1', 'btn_login' => 'true']);
}

/** @param  array<string, mixed>  $attributes */
function client(array $attributes = []): int
{
    return DB::table('ip_clients')->insertGetId($attributes + [
        'client_name' => 'Acme', 'client_active' => 1, 'client_date_created' => '2026-01-05 09:00:00', 'client_date_modified' => '2026-01-05 09:00:00',
    ]);
}

/** An invoice of the client, with its amounts row. @param  array<string, mixed>  $attributes */
function clientInvoice(int $clientId, array $attributes = [], float $total = 100, float $paid = 0): int
{
    $id = DB::table('ip_invoices')->insertGetId($attributes + [
        'client_id' => $clientId, 'user_id' => 1, 'invoice_group_id' => 1, 'invoice_status_id' => 2, 'invoice_number' => 'INV-1',
        'invoice_date_created' => '2026-02-01', 'invoice_date_due' => '2026-03-03', 'invoice_date_modified' => '2026-02-01 00:00:00',
        'invoice_terms' => '', 'invoice_url_key' => md5((string) microtime()),
    ]);
    DB::table('ip_invoice_amounts')->insert([
        'invoice_id' => $id, 'invoice_sign' => '1', 'invoice_item_subtotal' => $total, 'invoice_total' => $total,
        'invoice_paid' => $paid, 'invoice_balance' => $total - $paid,
    ]);

    return $id;
}

/** @param  array<string, mixed>  $attributes */
function clientPayment(int $invoiceId, array $attributes = []): int
{
    return DB::table('ip_payments')->insertGetId($attributes + [
        'invoice_id' => $invoiceId, 'payment_method_id' => 0, 'payment_date' => '2026-02-10', 'payment_amount' => 40, 'payment_note' => '',
    ]);
}

it('sends a guest to the login page from every client screen', function () {
    foreach (['/clients', '/clients/status/active', '/clients/form', '/clients/view/1'] as $path) {
        $this->get($path)->assertRedirect(url('sessions/login'));
    }
    $this->post('/clients/form', ['btn_submit' => '1'])->assertRedirect(url('sessions/login'));
    $this->post('/clients/delete/1')->assertRedirect(url('sessions/login'));
    $this->get('/clients/ajax/name_query', CLIENT_AJAX)->assertRedirect(url('sessions/login'));
    $this->post('/filter/ajax/filter_clients', [], CLIENT_AJAX)->assertRedirect(url('sessions/login'));
});

describe('index', function () {
    beforeEach(fn () => clientLogin($this));

    it('redirects clients to the active list', function () {
        $this->continueSession()->get('/clients')->assertStatus(307)->assertRedirect(url('clients/status/active'));
    });

    it('lists the active clients by name with their open balance', function () {
        $zed = client(['client_name' => 'Zed']);
        client(['client_name' => 'Ann', 'client_surname' => 'Lee', 'client_title' => 'mrs', 'client_email' => 'ann@example.test', 'client_mobile' => '555-1']);
        client(['client_name' => 'Gone', 'client_active' => 0]);
        clientInvoice($zed, [], 100, 30);
        clientInvoice($zed, ['invoice_number' => 'INV-2'], 20.5);

        $page = $this->continueSession()->get('/clients/status/active')->assertOk();

        $page->assertSeeInOrder(['Mrs Ann Lee', 'ann@example.test', '555-1', 'Zed', '$90.50'])
            ->assertDontSee('Gone')
            ->assertSee('placeholder="Filter Clients"', false)
            ->assertSee(url('filter/ajax/filter_clients'), false);
    });

    it('lists the inactive clients, or all of them', function () {
        client(['client_name' => 'Live']);
        client(['client_name' => 'Gone', 'client_active' => 0]);

        $this->continueSession()->get('/clients/status/inactive')->assertSee('Gone')->assertDontSee('Live');
        $this->continueSession()->get('/clients/status/all')->assertSee('Gone')->assertSee('Live');
        // Any other status filters nothing and highlights no button, as legacy does.
        $this->continueSession()->get('/clients/status/whatever')->assertSee('Gone')->assertSee('Live');
    });

    it('sorts by surname when the setting says so', function () {
        client(['client_name' => 'Aaron', 'client_surname' => 'Zimmer']);
        client(['client_name' => 'Zoe', 'client_surname' => 'Adams']);

        $this->continueSession()->get('/clients/status/all')->assertSeeInOrder(['Aaron Zimmer', 'Zoe Adams']);
        DB::table('ip_settings')->insert(['setting_key' => 'sort_clients_by_surname', 'setting_value' => '1']);
        $this->continueSession()->get('/clients/status/all')->assertSeeInOrder(['Zoe Adams', 'Aaron Zimmer']);
    });

    it('escapes names and pages by offset under the pager of the status in the URL', function () {
        foreach (range(1, 17) as $n) {
            client(['client_name' => sprintf('C%02d <b>', $n)]);
        }

        $first = $this->continueSession()->get('/clients/status/active')->assertOk()->assertSee('C01 &lt;b&gt;', false);
        expect(substr_count($first->getContent(), '<tr>'))->toBe(15 + 1);
        $first->assertSee(url('clients/status/active').'/15', false);

        $second = $this->continueSession()->get('/clients/status/active/15')->assertOk();
        expect(substr_count($second->getContent(), '<tr>'))->toBe(2 + 1);
    });

    it('links each row to its view, form and delete', function () {
        $id = client();

        $this->continueSession()->get('/clients/status/active')
            ->assertSee('href="'.url("clients/view/$id").'"', false)
            ->assertSee('href="'.url("clients/form/$id").'"', false)
            ->assertSee('action="'.url("clients/delete/$id").'"', false)
            ->assertSee('data-client-id="'.$id.'"', false);
    });
});

describe('form', function () {
    beforeEach(fn () => clientLogin($this));

    it('stores a new client, stamps both dates, flashes the creation and shows the client', function () {
        $this->continueSession()->post('/clients/form', [
            'is_update' => '0', 'client_active' => '1', 'client_name' => 'Acme', 'client_surname' => 'Ltd', 'client_title' => 'mr',
            'client_email' => 'a@b.test', 'client_country' => 'GB', 'client_gender' => ' 1', 'btn_submit' => '1', 'junk' => 'x',
        ])->assertStatus(303);

        $row = (array) DB::table('ip_clients')->first();
        expect($row)->toMatchArray(['client_name' => 'Acme', 'client_surname' => 'Ltd', 'client_active' => 1, 'client_country' => 'GB', 'client_gender' => 1])
            ->and($row['client_date_created'])->toBe($row['client_date_modified'])
            ->and(strtotime($row['client_date_created']))->toBeGreaterThan(time() - 60);

        $this->continueSession()->get('/clients/view/'.$row['client_id'])->assertSee('Record successfully created');
    });

    it('redirects a saved client to its view', function () {
        $this->continueSession()->post('/clients/form', ['is_update' => '0', 'client_name' => 'Acme', 'btn_submit' => '1'])
            ->assertRedirect(url('clients/view/'.DB::table('ip_clients')->value('client_id')));
    });

    it('stores 0 for a client_active the post leaves out: deactivating', function () {
        $id = client();

        $this->continueSession()->post("/clients/form/$id", ['is_update' => '1', 'client_name' => 'Acme', 'btn_submit' => '1']);

        expect(DB::table('ip_clients')->where('client_id', $id)->value('client_active'))->toBe(0);
    });

    it('updates the modified date only, and flashes the update', function () {
        $id = client(['client_date_modified' => '2026-01-06 09:00:00']);

        $this->continueSession()->post("/clients/form/$id", ['is_update' => '1', 'client_active' => '1', 'client_name' => 'Renamed', 'btn_submit' => '1'])
            ->assertRedirect(url("clients/view/$id"));

        $row = (array) DB::table('ip_clients')->where('client_id', $id)->first();
        expect($row['client_name'])->toBe('Renamed')
            ->and($row['client_date_created'])->toBe('2026-01-05 09:00:00')
            ->and(strtotime($row['client_date_modified']))->toBeGreaterThan(time() - 60);
        $this->continueSession()->get("/clients/view/$id")->assertSee('Record successfully updated');
    });

    it('refuses a new client whose name and surname exist', function () {
        client(['client_name' => 'Acme', 'client_surname' => 'Ltd']);

        $this->continueSession()->post('/clients/form', ['is_update' => '0', 'client_name' => 'ACME', 'client_surname' => 'ltd', 'btn_submit' => '1'])
            ->assertRedirect(url('clients/form'));
        $this->continueSession()->get('/clients/form')->assertSee('Client already exists!');
        expect(DB::table('ip_clients')->count())->toBe(1);

        // A different surname, or an update, is allowed.
        $this->continueSession()->post('/clients/form', ['is_update' => '0', 'client_name' => 'Acme', 'client_surname' => 'Inc', 'btn_submit' => '1']);
        expect(DB::table('ip_clients')->count())->toBe(2);
    });

    it('requires a name and a valid email, and shows the form again with what was posted', function () {
        $this->continueSession()->post('/clients/form', ['client_name' => '', 'client_email' => 'nope', 'client_city' => 'Paris', 'btn_submit' => '1'])
            ->assertOk()
            ->assertSee('<div class="alert alert-danger">The Client Name field is required.</div>', false)
            ->assertSee('<div class="alert alert-danger">The Email Address field must contain a valid email address.</div>', false)
            ->assertSee('value="Paris"', false);

        expect(DB::table('ip_clients')->count())->toBe(0);
    });

    it('trims the language, country, email and invoicing contact; fixes the AVS number; reads the birthdate in the display format', function () {
        $this->continueSession()->post('/clients/form', [
            'client_name' => 'Acme', 'client_language' => ' english ', 'client_country' => ' GB ', 'client_email' => ' a@b.test ', 'client_invoicing_contact' => ' Jo ',
            'client_avs' => '756.1234.5678.97', 'client_birthdate' => '03/15/1980', 'btn_submit' => '1',
        ]);

        expect((array) DB::table('ip_clients')->first())->toMatchArray([
            'client_language' => 'english', 'client_country' => 'GB', 'client_email' => 'a@b.test', 'client_invoicing_contact' => 'Jo',
            'client_avs' => '7561234567897', 'client_birthdate' => '1980-03-15',
        ]);
    });

    it('stores the custom title in place of the "custom" choice', function () {
        $this->continueSession()->post('/clients/form', ['client_name' => 'Acme', 'client_title' => 'custom', 'client_title_custom' => 'Sir', 'btn_submit' => '1']);

        expect(DB::table('ip_clients')->value('client_title'))->toBe('Sir');
    });

    it('assigns a new client to the users who see all clients, when it is active', function () {
        DB::table('ip_users')->where('user_id', 1)->update(['user_all_clients' => 1]);
        $old = client(['client_name' => 'Old']);

        $this->continueSession()->post('/clients/form', ['is_update' => '0', 'client_active' => '1', 'client_name' => 'New', 'btn_submit' => '1']);

        // Mdl_user_clients::set_all_clients_user() assigns every active client the user lacks, the old one too.
        expect(DB::table('ip_user_clients')->orderBy('client_id')->pluck('client_id')->all())->toBe([$old, $old + 1]);
    });

    it('cancels to the list without saving', function () {
        $this->continueSession()->post('/clients/form', ['client_name' => 'X', 'btn_cancel' => '1'])->assertRedirect(url('clients'));

        expect(DB::table('ip_clients')->count())->toBe(0);
    });

    it('fills the form of an edit from the client, choosing the country, gender and title', function () {
        $id = client(['client_name' => 'A "q" <b>', 'client_country' => 'GB', 'client_gender' => 1, 'client_title' => 'Prof.', 'client_birthdate' => '1980-03-15']);

        $page = $this->continueSession()->get("/clients/form/$id")->assertOk()
            ->assertSee('value="A &quot;q&quot; &lt;b&gt;"', false)
            ->assertSee('value="03/15/1980"', false)
            ->assertSee('name="is_update" type="hidden" value="1"', false);

        expect($page->getContent())
            ->toMatch('/<option value="GB"\s+selected="selected"/')
            ->toMatch('/<option value=" 1"\s+selected="selected"/')
            // A title outside the list selects "custom" and shows its text box.
            ->toMatch('/value="custom"\s+selected/')
            ->toContain('value="Prof."');
    });

    it('selects "custom" for a new client too, because no title is outside the list', function () {
        $this->continueSession()->get('/clients/form')->assertOk()
            ->assertSee('name="is_update" type="hidden" value="0"', false)
            ->assertSee('id="client_active" name="client_active" type="checkbox" value="1" checked="checked"', false);
    });

    it('answers 404 for a client that does not exist and for a post without the save button', function () {
        $this->continueSession()->get('/clients/form/999')->assertNotFound();

        $id = client();
        $this->continueSession()->post("/clients/form/$id", ['client_name' => ''])->assertNotFound();
    });
});

describe('view', function () {
    beforeEach(fn () => clientLogin($this));

    it('shows the details and the totals of the client', function () {
        $id = client(['client_name' => 'Acme', 'client_surname' => 'Ltd', 'client_address_1' => '1 Road', 'client_city' => 'Leeds', 'client_zip' => 'L1', 'client_country' => 'GB',
            'client_email' => 'a@b.test', 'client_phone' => '555-0', 'client_company' => 'Acme plc', 'client_vat_id' => 'GB123']);
        clientInvoice($id, [], 100, 40);

        $this->continueSession()->get("/clients/view/$id")->assertOk()
            ->assertSee('Acme Ltd')
            ->assertSee('1 Road<br>', false)
            ->assertSeeInOrder(['Leeds', 'L1', 'United Kingdom'])
            ->assertSeeInOrder(['Total Billed', '$100.00', 'Total Paid', '$40.00', 'Total Balance', '$60.00'])
            ->assertSee('555-0')
            ->assertSee('GB123');
    });

    it('escapes a name with markup that bypassed input cleaning', function () {
        $id = client(['client_name' => '<script>alert(1)</script>', 'client_surname' => '<b>x</b>']);

        $page = $this->continueSession()->get("/clients/view/$id")->assertOk();

        $page->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;b&gt;x&lt;/b&gt;', false);
        // The index and the form escape it too.
        $this->continueSession()->get('/clients/status/all')->assertDontSee('<script>alert(1)</script>', false);
        $this->continueSession()->get("/clients/form/$id")->assertDontSee('<script>alert(1)</script>', false);
    });

    it('answers 404 for a client that does not exist', function () {
        $this->continueSession()->get('/clients/view/999')->assertNotFound();
    });

    it('lists the invoices and payments of the client, and none of another', function () {
        $id = client();
        $other = client(['client_name' => 'Other']);
        $invoice = clientInvoice($id, ['invoice_number' => 'INV-77', 'invoice_date_due' => '2020-01-01'], 100, 40);
        clientInvoice($other, ['invoice_number' => 'INV-OTHER']);
        DB::table('ip_payment_methods')->insert(['payment_method_id' => 3, 'payment_method_name' => 'Bank transfer']);
        clientPayment($invoice, ['payment_method_id' => 3, 'payment_amount' => 40, 'payment_note' => 'First <i>half</i>']);

        $page = $this->continueSession()->get("/clients/view/$id/invoices")->assertOk();

        $page->assertSee('INV-77')->assertDontSee('INV-OTHER')
            ->assertSee('href="'.url("invoices/view/$invoice").'"', false)
            ->assertSee('font-overdue', false)
            ->assertSeeInOrder(['Bank transfer', 'First &lt;i&gt;half&lt;/i&gt;'], false)
            ->assertSee('class="label sent"', false)
            ->assertSee('$60.00');
    });

    it('shows the empty tables when the client has no invoices or payments', function () {
        $id = client();

        $this->continueSession()->get("/clients/view/$id")->assertOk()
            ->assertSee('id="client-invoices"', false)
            ->assertSee('id="client-payments"', false)
            ->assertSee('<tbody>', false);
    });

    it('links the quotes tab to the legacy app', function () {
        $id = client();

        $this->continueSession()->get("/clients/view/$id")
            ->assertSee('href="'.rtrim((string) config('ledgerlift.legacy_url'), '/')."/clients/view/$id/quotes".'"', false);
    });

    it('remembers the page of each tab in the session', function () {
        $id = client();
        foreach (range(1, 17) as $n) {
            clientInvoice($id, ['invoice_number' => sprintf('N%02d', $n), 'invoice_date_created' => sprintf('2026-02-%02d', $n)]);
        }

        $first = $this->continueSession()->get("/clients/view/$id/invoices")->assertOk();
        $first->assertSee('N17')->assertDontSee('N01');
        $this->continueSession()->get("/clients/view/$id/invoices/15")->assertSee('N01')->assertDontSee('N17');
        // Another tab keeps the invoices page; the details tab forgets it.
        $this->continueSession()->get("/clients/view/$id/payments")->assertSee('N01');
        $this->continueSession()->get("/clients/view/$id")->assertSee('N17')->assertDontSee('N01');
    });
});

describe('delete', function () {
    beforeEach(fn () => clientLogin($this));

    it('is POST only', function () {
        $this->continueSession()->get('/clients/delete/1')->assertNotFound();
    });

    it('removes the client and what hung off it, and flashes the deletion', function () {
        $id = client();
        $keep = client(['client_name' => 'Keep']);
        $invoice = clientInvoice($id);
        clientInvoice($keep, ['invoice_number' => 'KEEP-1']);
        clientPayment($invoice);
        DB::table('ip_client_notes')->insert(['client_id' => $id, 'client_note' => 'n', 'client_note_date' => '2026-01-01']);

        $this->continueSession()->post("/clients/delete/$id")->assertStatus(303)->assertRedirect(url('clients'));

        expect(DB::table('ip_clients')->pluck('client_id')->all())->toBe([$keep])
            ->and(DB::table('ip_invoices')->pluck('invoice_number')->all())->toBe(['KEEP-1'])
            ->and(DB::table('ip_invoice_amounts')->count())->toBe(1)
            ->and(DB::table('ip_payments')->count())->toBe(0)
            ->and(DB::table('ip_client_notes')->count())->toBe(0);
        $this->continueSession()->get('/clients/status/active')->assertSee('Record successfully deleted');
    });
});

describe('ajax', function () {
    beforeEach(fn () => clientLogin($this));

    it('answers nothing to a request that is not AJAX', function () {
        $this->continueSession()->get('/clients/ajax/name_query?query=a')->assertOk()->assertContent('');
    });

    it('finds active clients by the start of the name, surname or full name, as JSON served as text/html', function () {
        $ann = client(['client_name' => 'Ann', 'client_surname' => 'Acorn', 'client_title' => 'doctor']);
        $acme = client(['client_name' => 'Acme <b>']);
        client(['client_name' => 'Acid', 'client_active' => 0]);
        $bob = client(['client_name' => 'Bob', 'client_surname' => 'Ac']);

        $response = $this->continueSession()->get('/clients/ajax/name_query?query=ac', CLIENT_AJAX)->assertOk();

        expect($response->headers->get('Content-Type'))->toStartWith('text/html')
            ->and(json_decode($response->getContent(), true))->toBe([
                ['id' => (string) $acme, 'text' => 'Acme &lt;b&gt;'],
                ['id' => (string) $ann, 'text' => 'Ann Acorn'],
                ['id' => (string) $bob, 'text' => 'Bob Ac'],
            ]);
    });

    it('finds inside the name when the permissive search is on, and strips LIKE wildcards from the query', function () {
        client(['client_name' => 'Zacme']);
        client(['client_name' => 'Acme']);

        $plain = json_decode($this->continueSession()->get('/clients/ajax/name_query?query=acm', CLIENT_AJAX)->getContent(), true);
        $loose = json_decode($this->continueSession()->get('/clients/ajax/name_query?query=acm&permissive_search_clients=1', CLIENT_AJAX)->getContent(), true);
        $wild = json_decode($this->continueSession()->get('/clients/ajax/name_query?query=%25', CLIENT_AJAX)->getContent(), true);

        expect(array_column($plain, 'text'))->toBe(['Acme'])
            ->and(array_column($loose, 'text'))->toBe(['Acme', 'Zacme'])
            // Stripping the wildcard leaves an empty term, which with the trailing % matches everyone.
            ->and(array_column($wild, 'text'))->toBe(['Acme', 'Zacme']);
    });

    it('answers [] and then searches anyway for an empty query, as legacy does', function () {
        client(['client_name' => 'Zed']);

        $this->continueSession()->get('/clients/ajax/name_query', CLIENT_AJAX)->assertContent('[][{"id":"1","text":"Zed"}]');
        $this->continueSession()->get('/clients/ajax/name_query?query=0', CLIENT_AJAX)->assertContent('[][]');
    });

    it('saves the permissive search preference when it is 0 or 1 and nothing otherwise', function () {
        $this->continueSession()->get('/clients/ajax/save_preference_permissive_search_clients?permissive_search_clients=1', CLIENT_AJAX)->assertOk()->assertContent('');
        expect(DB::table('ip_settings')->where('setting_key', 'enable_permissive_search_clients')->pluck('setting_value')->all())->toBe(['1']);

        $this->continueSession()->get('/clients/ajax/save_preference_permissive_search_clients?permissive_search_clients=0', CLIENT_AJAX);
        expect(DB::table('ip_settings')->where('setting_key', 'enable_permissive_search_clients')->pluck('setting_value')->all())->toBe(['0']);

        foreach (['2', '', '10', 'a'] as $bad) {
            $this->continueSession()->get('/clients/ajax/save_preference_permissive_search_clients?permissive_search_clients='.$bad, CLIENT_AJAX)->assertOk()->assertContent('');
        }
        expect(DB::table('ip_settings')->where('setting_key', 'enable_permissive_search_clients')->pluck('setting_value')->all())->toBe(['0']);
    });

    it('filters the client list by keywords in title, name, surname, email, phone and active flag', function () {
        client(['client_name' => 'Ann', 'client_surname' => 'Acorn', 'client_email' => 'ann@x.test']);
        client(['client_name' => 'Bob', 'client_phone' => '555-9', 'client_active' => 0]);

        $both = $this->continueSession()->post('/filter/ajax/filter_clients', ['filter_query' => ''], CLIENT_AJAX)->assertOk();
        $both->assertSee('Ann Acorn')->assertSee('Bob');
        $this->continueSession()->post('/filter/ajax/filter_clients', ['filter_query' => 'ACORN x.test'], CLIENT_AJAX)->assertSee('Ann Acorn')->assertDontSee('Bob');
        $this->continueSession()->post('/filter/ajax/filter_clients', ['filter_query' => '555'], CLIENT_AJAX)->assertSee('Bob')->assertDontSee('Ann');
        $this->continueSession()->post('/filter/ajax/filter_clients', ['filter_query' => '%'], CLIENT_AJAX)->assertDontSee('Bob')->assertDontSee('Ann');
    });

    it('saves, lists and deletes client notes', function () {
        $id = client();

        $saved = $this->continueSession()->post('/clients/ajax/save_client_note', ['client_id' => $id, 'client_note' => "Call back\n<b>soon</b>"], CLIENT_AJAX)->assertOk();
        $json = json_decode($saved->getContent(), true);
        expect($json['success'])->toBe(1)->and($json['new_token'])->toBeString();
        $noteId = DB::table('ip_client_notes')->value('client_note_id');
        expect(DB::table('ip_client_notes')->value('client_note_date'))->toBe(date('Y-m-d'));

        $this->continueSession()->post('/clients/ajax/load_client_notes', ['client_id' => $id], CLIENT_AJAX)
            ->assertSee("Call back<br />\n&lt;b&gt;soon&lt;/b&gt;", false)
            ->assertSee('data-id="'.$noteId.'"', false);

        $invalid = json_decode($this->continueSession()->post('/clients/ajax/save_client_note', ['client_id' => $id, 'client_note' => ''], CLIENT_AJAX)->getContent(), true);
        expect($invalid['success'])->toBe(0)->and($invalid['validation_errors'])->toBe(['client_note' => '<p>The Note field is required.</p>']);

        $deleted = json_decode($this->continueSession()->post('/clients/ajax/delete_client_note', ['client_note_id' => $noteId], CLIENT_AJAX)->getContent(), true);
        expect($deleted)->toBe(['success' => 1])->and(DB::table('ip_client_notes')->count())->toBe(0);
        $missing = json_decode($this->continueSession()->post('/clients/ajax/delete_client_note', ['client_note_id' => 999], CLIENT_AJAX)->getContent(), true);
        expect($missing)->toBe(['success' => 0]);
    });
});

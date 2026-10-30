<?php

use App\Models\Family;
use App\Models\TaxRate;
use Illuminate\Support\Facades\DB;

const REFERENCE_MODULES = ['tax_rates', 'families', 'units', 'payment_methods', 'invoice_groups'];

function referenceLogin(object $test): void
{
    $test->post('/sessions/login', ['email' => 'admin@ledgerlift.test', 'password' => 'ledgerlift-demo-1', 'btn_login' => 'true']);
}

function taxRates(int $count): void
{
    DB::table('ip_tax_rates')->insert(array_map(
        fn (int $n) => ['tax_rate_name' => "Rate $n", 'tax_rate_percent' => $n],
        range(1, $count),
    ));
}

it('sends a guest to the login page from every screen', function (string $module) {
    $this->get("/$module")->assertRedirect(url('sessions/login'));
    $this->get("/$module/index/15")->assertRedirect(url('sessions/login'));
    $this->get("/$module/form")->assertRedirect(url('sessions/login'));
    $this->post("/$module/form", ['btn_submit' => '1'])->assertRedirect(url('sessions/login'));
    $this->post("/$module/delete/1")->assertRedirect(url('sessions/login'));
})->with(REFERENCE_MODULES);

describe('index', function () {
    beforeEach(fn () => referenceLogin($this));

    it('pages by offset, fifteen rows to a page', function () {
        taxRates(17);

        $first = $this->continueSession()->get('/tax_rates')->assertOk();
        expect(substr_count($first->getContent(), '<tr>'))->toBe(15 + 1) // + the header row
            ->and($first->getContent())->toContain('href="'.url('tax_rates/index/15').'" title="Next"')
            ->and($first->getContent())->toContain('href="'.url('tax_rates/index/15').'" title="Last"')
            ->and($first->getContent())->toContain('class="btn btn-default disabled" href="#" title="Prev"');

        $second = $this->continueSession()->get('/tax_rates/index/15')->assertOk();
        expect(substr_count($second->getContent(), '<tr>'))->toBe(2 + 1)
            ->and($second->getContent())->toContain('href="'.url('tax_rates/index/0').'" title="First"')
            ->and($second->getContent())->toContain('class="btn btn-default disabled" href="#" title="Next"');
    });

    it('takes the page size from default_list_limit', function () {
        taxRates(7);
        DB::table('ip_settings')->insert(['setting_key' => 'default_list_limit', 'setting_value' => '5']);

        $this->continueSession()->get('/tax_rates/index/5')->assertOk()
            ->assertSee('Rate 6', false)->assertSee('Rate 7', false)->assertDontSee('Rate 5<', false)
            ->assertSee('href="'.url('tax_rates/index/0').'" title="Prev"', false);
    });

    it('lists payment methods in table order and the others sorted', function () {
        DB::table('ip_payment_methods')->insert([['payment_method_name' => 'Zeta'], ['payment_method_name' => 'Alpha']]);
        DB::table('ip_units')->insert([
            ['unit_name' => 'week', 'unit_name_plrl' => 'weeks'], ['unit_name' => 'day', 'unit_name_plrl' => 'days'],
        ]);

        $this->continueSession()->get('/payment_methods')->assertSeeInOrder(['Zeta', 'Alpha']);
        $this->continueSession()->get('/units')->assertSeeInOrder(['day', 'week']);
    });

    it('escapes record names', function () {
        Family::query()->insert(['family_name' => 'Fish & "Chips"']);

        $this->continueSession()->get('/families')->assertSee('Fish &amp; &quot;Chips&quot;', false);
    });
});

describe('form', function () {
    beforeEach(fn () => referenceLogin($this));

    it('stores a tax rate and flashes the creation on the list', function () {
        $this->continueSession()->post('/tax_rates/form', ['tax_rate_name' => 'VAT', 'tax_rate_percent' => '7.5', 'btn_submit' => '1', 'junk' => 'x'])
            ->assertStatus(303)->assertRedirect(url('tax_rates'));

        expect(DB::table('ip_tax_rates')->get()->all())->toHaveCount(1)
            ->and((array) DB::table('ip_tax_rates')->first())->toMatchArray(['tax_rate_name' => 'VAT', 'tax_rate_percent' => '7.50']);

        $this->continueSession()->get('/tax_rates')->assertSee('Record successfully created')->assertSee('7.50%', false);
    });

    it('reads the percent in the decimal point of the settings', function () {
        DB::table('ip_settings')->insert([
            ['setting_key' => 'decimal_point', 'setting_value' => ','], ['setting_key' => 'thousands_separator', 'setting_value' => '.'],
        ]);

        $this->continueSession()->post('/tax_rates/form', ['tax_rate_name' => 'VAT', 'tax_rate_percent' => '7,5', 'btn_submit' => '1']);

        expect(DB::table('ip_tax_rates')->value('tax_rate_percent'))->toBe('7.50');
    });

    it('edits a record and fills the form from it', function () {
        $id = DB::table('ip_tax_rates')->insertGetId(['tax_rate_name' => 'Old', 'tax_rate_percent' => 15]);

        $this->continueSession()->get("/tax_rates/form/$id")->assertOk()
            ->assertSee('value="Old"', false)->assertSee('value="15.00"', false);

        $this->continueSession()->post("/tax_rates/form/$id", ['tax_rate_name' => 'New', 'tax_rate_percent' => '16', 'btn_submit' => '1'])
            ->assertRedirect(url('tax_rates'));

        expect(TaxRate::query()->find($id)->tax_rate_name)->toBe('New');
        $this->continueSession()->get('/tax_rates')->assertSee('Record successfully updated');
    });

    it('answers 404 for a record that does not exist, and for a post without the save button', function () {
        $this->continueSession()->get('/tax_rates/form/999')->assertNotFound();

        // MY_Model::prep_form() returns null once something was posted.
        $id = DB::table('ip_tax_rates')->insertGetId(['tax_rate_name' => 'A', 'tax_rate_percent' => 1]);
        $this->continueSession()->post("/tax_rates/form/$id", ['tax_rate_name' => ''])->assertNotFound();
    });

    it('shows each missing field again with the posted values, as CI words it', function () {
        $this->continueSession()->post('/tax_rates/form', ['tax_rate_name' => '  ', 'tax_rate_percent' => '8', 'btn_submit' => '1'])
            ->assertOk()
            ->assertSee('<div class="alert alert-danger">The Tax Rate Name field is required.</div>', false)
            ->assertSee('value="  "', false)
            ->assertSee('value="8.00"', false);

        expect(DB::table('ip_tax_rates')->count())->toBe(0);
    });

    it('cancels without saving', function () {
        $this->continueSession()->post('/tax_rates/form', ['tax_rate_name' => 'X', 'tax_rate_percent' => '1', 'btn_cancel' => '1'])
            ->assertRedirect(url('tax_rates'));

        expect(DB::table('ip_tax_rates')->count())->toBe(0);
    });

    it('refuses a new family whose name exists, in any letter case', function () {
        Family::query()->insert(['family_name' => 'Services']);

        $this->continueSession()->post('/families/form', ['family_name' => 'services', 'is_update' => '0', 'btn_submit' => '1'])
            ->assertRedirect(url('families/form'));
        $this->continueSession()->get('/families/form')->assertSee('Family already exists!');

        expect(Family::query()->count())->toBe(1);
    });

    it('lets a family keep its name when it is edited', function () {
        $id = Family::query()->insertGetId(['family_name' => 'Services']);

        $this->continueSession()->get("/families/form/$id")->assertSee('name="is_update" type="hidden" value="1"', false);
        $this->continueSession()->post("/families/form/$id", ['family_name' => 'Services', 'is_update' => '1', 'btn_submit' => '1'])
            ->assertRedirect(url('families'));
    });

    it('stores the identifier format, next id and left pad of an invoice group', function () {
        $this->continueSession()->post('/invoice_groups/form', [
            'invoice_group_name' => 'Pro forma', 'invoice_group_identifier_format' => 'PF-{{{year}}}-{{{id}}}',
            'invoice_group_next_id' => '7', 'invoice_group_left_pad' => '3', 'btn_submit' => '1',
        ])->assertRedirect(url('invoice_groups'));

        expect((array) DB::table('ip_invoice_groups')->where('invoice_group_name', 'Pro forma')->first())->toMatchArray([
            'invoice_group_identifier_format' => 'PF-{{{year}}}-{{{id}}}', 'invoice_group_next_id' => 7, 'invoice_group_left_pad' => 3,
        ]);
    });

    it('resets the counter fields of a new invoice group after a failed save', function () {
        $this->continueSession()->post('/invoice_groups/form', [
            'invoice_group_name' => '', 'invoice_group_identifier_format' => 'X', 'invoice_group_next_id' => '9', 'invoice_group_left_pad' => '5', 'btn_submit' => '1',
        ])->assertOk()
            ->assertSee('The Name field is required.')
            ->assertSee('value="X"', false)
            ->assertSee('id="invoice_group_next_id" class="form-control"'."\n".'                           value="1"', false)
            ->assertSee('id="invoice_group_left_pad" class="form-control"'."\n".'                           value="0"', false);
    });

    it('stores a posted value as the XSS filter leaves it', function () {
        $this->continueSession()->post('/units/form', ['unit_name' => '<script>alert(1)</script>', 'unit_name_plrl' => 'p', 'is_update' => '0', 'btn_submit' => '1']);

        expect(DB::table('ip_units')->value('unit_name'))->toBe('[removed]alert&#40;1&#41;[removed]');
    });
});

describe('delete', function () {
    beforeEach(fn () => referenceLogin($this));

    it('is POST only', function (string $module) {
        $this->continueSession()->get("/$module/delete/1")->assertNotFound();
        $this->continueSession()->put("/$module/delete/1")->assertNotFound();
    })->with(REFERENCE_MODULES);

    it('removes the record and flashes the deletion on the list', function () {
        $id = DB::table('ip_tax_rates')->insertGetId(['tax_rate_name' => 'Gone', 'tax_rate_percent' => 1]);
        $keep = DB::table('ip_tax_rates')->insertGetId(['tax_rate_name' => 'Kept', 'tax_rate_percent' => 2]);

        $this->continueSession()->post("/tax_rates/delete/$id")->assertStatus(303)->assertRedirect(url('tax_rates'));

        expect(DB::table('ip_tax_rates')->pluck('tax_rate_id')->all())->toBe([$keep]);
        $this->continueSession()->get('/tax_rates')->assertSee('Record successfully deleted');
    });

    it('needs the CSRF token', function () {
        $id = DB::table('ip_tax_rates')->insertGetId(['tax_rate_name' => 'Safe', 'tax_rate_percent' => 1]);
        $this->enforceCsrf();

        $this->continueSession()->post("/tax_rates/delete/$id")->assertForbidden();

        expect(DB::table('ip_tax_rates')->count())->toBe(1);
    });
});

describe('family filter', function () {
    beforeEach(fn () => referenceLogin($this));

    it('lists the families holding every keyword, case-insensitively', function () {
        Family::query()->insert([['family_name' => 'Web Hosting'], ['family_name' => 'Web Design'], ['family_name' => '100% Cotton']]);
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        $this->continueSession()->post('/filter/ajax/filter_families', ['filter_query' => 'web HOST'], $ajax)
            ->assertSee('Web Hosting')->assertDontSee('Web Design');
        $this->continueSession()->post('/filter/ajax/filter_families', ['filter_query' => '0%'], $ajax)
            ->assertSee('100% Cotton')->assertDontSee('Web');
        $this->continueSession()->post('/filter/ajax/filter_families', ['filter_query' => '%'], $ajax)
            ->assertSee('100% Cotton')->assertDontSee('Web');
    });

    it('answers nothing to a request that is not AJAX', function () {
        $this->continueSession()->post('/filter/ajax/filter_families', ['filter_query' => 'a'])->assertOk()->assertContent('');
    });

    it('shows the search box on the families list only', function () {
        $this->continueSession()->get('/families')->assertSee('placeholder="Filter Families"', false)
            ->assertSee(url('filter/ajax/filter_families'), false);
        $this->continueSession()->get('/units')->assertDontSee('id="filter"', false);
    });
});

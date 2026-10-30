<?php

use Illuminate\Support\Facades\DB;

const PRODUCT_AJAX = ['X-Requested-With' => 'XMLHttpRequest'];

function productLogin(object $test): void
{
    $test->post('/sessions/login', ['email' => 'admin@ledgerlift.test', 'password' => 'ledgerlift-demo-1', 'btn_login' => 'true']);
}

/** @return array{family: int, unit: int, tax: int} */
function productLookups(): array
{
    return [
        'family' => DB::table('ip_families')->insertGetId(['family_name' => 'Services']),
        'unit' => DB::table('ip_units')->insertGetId(['unit_name' => 'hour', 'unit_name_plrl' => 'hours']),
        'tax' => DB::table('ip_tax_rates')->insertGetId(['tax_rate_name' => 'VAT', 'tax_rate_percent' => 20]),
    ];
}

/** @param  array<string, mixed>  $attributes */
function product(array $attributes = []): int
{
    return DB::table('ip_products')->insertGetId($attributes + ['product_name' => 'Widget', 'product_description' => '', 'product_price' => 10]);
}

it('sends a guest to the login page from every product screen', function () {
    $this->get('/products')->assertRedirect(url('sessions/login'));
    $this->get('/products/index/15')->assertRedirect(url('sessions/login'));
    $this->get('/products/form')->assertRedirect(url('sessions/login'));
    $this->post('/products/form', ['btn_submit' => '1'])->assertRedirect(url('sessions/login'));
    $this->post('/products/delete/1')->assertRedirect(url('sessions/login'));
    $this->get('/products/ajax/modal_product_lookups', PRODUCT_AJAX)->assertRedirect(url('sessions/login'));
    $this->post('/products/ajax/process_product_selections', [], PRODUCT_AJAX)->assertRedirect(url('sessions/login'));
    $this->post('/filter/ajax/filter_products', [], PRODUCT_AJAX)->assertRedirect(url('sessions/login'));
});

describe('index', function () {
    beforeEach(fn () => productLogin($this));

    it('lists products by family name, then product name, with unit and tax rate names', function () {
        $lookups = productLookups();
        $other = DB::table('ip_families')->insertGetId(['family_name' => 'Hardware']);
        product(['product_name' => 'Zebra', 'family_id' => $lookups['family'], 'unit_id' => $lookups['unit'], 'tax_rate_id' => $lookups['tax']]);
        product(['product_name' => 'Anvil', 'family_id' => $lookups['family']]);
        product(['product_name' => 'Mallet', 'family_id' => $other]);
        product(['product_name' => 'Loose']);

        $page = $this->continueSession()->get('/products')->assertOk();

        $page->assertSeeInOrder(['Loose', 'Mallet', 'Anvil', 'Zebra'])
            ->assertSee('<td>hour</td>', false)
            ->assertSee('<td>VAT</td>', false)
            ->assertSee('<td>None</td>', false)
            ->assertSee('placeholder="Filter Products"', false)
            ->assertSee(url('filter/ajax/filter_products'), false);
    });

    it('formats prices as currency and keeps description line breaks', function () {
        product(['product_price' => 1234.5, 'product_description' => "line one\nline <two>"]);

        $this->continueSession()->get('/products')
            ->assertSee('$1,234.50', false)
            ->assertSee("line one<br />\nline &lt;two&gt;", false);
    });

    it('pages by offset', function () {
        foreach (range(1, 17) as $n) {
            product(['product_name' => sprintf('P%02d', $n)]);
        }

        $first = $this->continueSession()->get('/products')->assertOk();
        expect(substr_count($first->getContent(), '<tr>'))->toBe(15 + 1);
        $second = $this->continueSession()->get('/products/index/15')->assertOk();
        expect(substr_count($second->getContent(), '<tr>'))->toBe(2 + 1);
    });

    it('shows the tariff column when sumex is on', function () {
        $this->continueSession()->get('/products')->assertDontSee('Tariff');
        DB::table('ip_settings')->insert(['setting_key' => 'sumex', 'setting_value' => '1']);
        $this->continueSession()->get('/products')->assertSee('Tariff');
    });
});

describe('form', function () {
    beforeEach(fn () => productLogin($this));

    it('stores a product with its family, unit and tax rate and flashes the creation', function () {
        $lookups = productLookups();

        $this->continueSession()->post('/products/form', [
            'family_id' => $lookups['family'], 'unit_id' => $lookups['unit'], 'tax_rate_id' => $lookups['tax'],
            'product_sku' => 'W-1', 'product_name' => 'Widget', 'product_description' => 'A widget',
            'product_price' => '12.5', 'purchase_price' => '7', 'provider_name' => 'Acme', 'btn_submit' => '1', 'junk' => 'x',
        ])->assertStatus(303)->assertRedirect(url('products'));

        expect((array) DB::table('ip_products')->first())->toMatchArray([
            'family_id' => $lookups['family'], 'unit_id' => $lookups['unit'], 'tax_rate_id' => $lookups['tax'],
            'product_sku' => 'W-1', 'product_price' => '12.50', 'purchase_price' => '7.00', 'provider_name' => 'Acme',
        ]);
        $this->continueSession()->get('/products')->assertSee('Record successfully created');
    });

    it('stores NULL for the 0 of an unselected family, unit and tax rate, and for empty prices', function () {
        $this->continueSession()->post('/products/form', [
            'family_id' => '0', 'unit_id' => '0', 'tax_rate_id' => '0', 'product_name' => 'Bare',
            'product_price' => '0', 'purchase_price' => '', 'btn_submit' => '1',
        ])->assertRedirect(url('products'));

        expect((array) DB::table('ip_products')->first())->toMatchArray([
            'family_id' => null, 'unit_id' => null, 'tax_rate_id' => null, 'product_price' => null, 'purchase_price' => null,
        ]);
    });

    it('nulls the lookups and prices an edit leaves out, as db_array() does', function () {
        $lookups = productLookups();
        $id = product(['family_id' => $lookups['family'], 'unit_id' => $lookups['unit'], 'tax_rate_id' => $lookups['tax'], 'purchase_price' => 5]);

        $this->continueSession()->post("/products/form/$id", ['product_name' => 'Renamed', 'product_price' => '9', 'btn_submit' => '1']);

        expect((array) DB::table('ip_products')->where('product_id', $id)->first())->toMatchArray([
            'product_name' => 'Renamed', 'family_id' => null, 'unit_id' => null, 'tax_rate_id' => null, 'purchase_price' => null, 'product_price' => '9.00',
        ]);
    });

    it('reads prices in the decimal point and thousands separator of the settings', function (array $settings, string $input, string $stored) {
        DB::table('ip_settings')->insert(array_map(fn ($key, $value) => ['setting_key' => $key, 'setting_value' => $value], array_keys($settings), $settings));

        $this->continueSession()->post('/products/form', ['product_name' => 'Priced', 'product_price' => $input, 'purchase_price' => $input, 'btn_submit' => '1']);

        expect((array) DB::table('ip_products')->first())->toMatchArray(['product_price' => $stored, 'purchase_price' => $stored]);
    })->with([
        'defaults' => [[], '1.234,56', '1.23'],
        'european' => [['decimal_point' => ',', 'thousands_separator' => '.'], '1.234,56', '1234.56'],
    ]);

    it('requires a name and a price, and only numbers for family, unit and tax rate', function () {
        $this->continueSession()->post('/products/form', [
            'product_name' => '  ', 'product_price' => '', 'family_id' => 'abc', 'unit_id' => '1x', 'tax_rate_id' => '2', 'btn_submit' => '1',
        ])->assertOk()
            ->assertSee('<div class="alert alert-danger">The Product name field is required.</div>', false)
            ->assertSee('<div class="alert alert-danger">The Price field is required.</div>', false)
            ->assertSee('<div class="alert alert-danger">The Family field must contain only numbers.</div>', false)
            ->assertSee('<div class="alert alert-danger">The Unit field must contain only numbers.</div>', false)
            ->assertDontSee('The Tax Rate field');

        expect(DB::table('ip_products')->count())->toBe(0);
    });

    it('fills the form of an edit from the product, with the selections made', function () {
        $lookups = productLookups();
        $id = product([
            'family_id' => $lookups['family'], 'unit_id' => $lookups['unit'], 'tax_rate_id' => $lookups['tax'],
            'product_name' => 'A "quoted" <b>name</b>', 'product_price' => 1234.5,
        ]);

        $page = $this->continueSession()->get("/products/form/$id")->assertOk()
            ->assertSee("#$id", false)
            ->assertSee('value="A &quot;quoted&quot; &lt;b&gt;name&lt;/b&gt;"', false)
            ->assertSee('value="1,234.50"', false)
            ->assertSee('hour/hours')
            ->assertSee('VAT (20.00%)');

        foreach ([$lookups['family'], $lookups['unit'], $lookups['tax']] as $selected) {
            expect($page->getContent())->toMatch('/<option value="'.$selected.'"\s+selected="selected"/');
        }
    });

    it('answers 404 for a product that does not exist and for a post without the save button', function () {
        $this->continueSession()->get('/products/form/999')->assertNotFound();

        $id = product();
        $this->continueSession()->post("/products/form/$id", ['product_name' => ''])->assertNotFound();
    });

    it('cancels without saving', function () {
        $this->continueSession()->post('/products/form', ['product_name' => 'X', 'product_price' => '1', 'btn_cancel' => '1'])
            ->assertRedirect(url('products'));

        expect(DB::table('ip_products')->count())->toBe(0);
    });

    it('shows the tariff field when sumex is on', function () {
        $this->continueSession()->get('/products/form')->assertDontSee('product_tariff');
        DB::table('ip_settings')->insert(['setting_key' => 'sumex', 'setting_value' => '1']);
        $this->continueSession()->get('/products/form')->assertSee('name="product_tariff"', false);
    });
});

describe('delete', function () {
    beforeEach(fn () => productLogin($this));

    it('is POST only', function () {
        $this->continueSession()->get('/products/delete/1')->assertNotFound();
    });

    it('removes the product and flashes the deletion on the list', function () {
        $id = product(['product_name' => 'Gone']);
        $keep = product(['product_name' => 'Kept']);

        $this->continueSession()->post("/products/delete/$id")->assertStatus(303)->assertRedirect(url('products'));

        expect(DB::table('ip_products')->pluck('product_id')->all())->toBe([$keep]);
        $this->continueSession()->get('/products')->assertSee('Record successfully deleted');
    });
});

describe('search', function () {
    beforeEach(fn () => productLogin($this));

    it('lists the products holding every keyword in sku, family, name, description, price or tariff', function () {
        $family = DB::table('ip_families')->insertGetId(['family_name' => 'Web Hosting']);
        product(['product_name' => 'Basic plan', 'family_id' => $family, 'product_sku' => 'HST-1']);
        product(['product_name' => 'Design day', 'product_description' => '100% custom', 'product_sku' => 'DSN']);

        $this->continueSession()->post('/filter/ajax/filter_products', ['filter_query' => 'hosting BASIC'], PRODUCT_AJAX)
            ->assertSee('Basic plan')->assertDontSee('Design day');
        $this->continueSession()->post('/filter/ajax/filter_products', ['filter_query' => '0%'], PRODUCT_AJAX)
            ->assertSee('Design day')->assertDontSee('Basic plan');
        $this->continueSession()->post('/filter/ajax/filter_products', ['filter_query' => ''], PRODUCT_AJAX)
            ->assertSee('Design day')->assertSee('Basic plan');
    });

    it('answers nothing to a request that is not AJAX', function () {
        $this->continueSession()->post('/filter/ajax/filter_products', ['filter_query' => 'a'])->assertOk()->assertContent('');
    });
});

describe('lookup modal', function () {
    beforeEach(fn () => productLogin($this));

    it('answers nothing to a request that is not AJAX', function () {
        $this->continueSession()->get('/products/ajax/modal_product_lookups')->assertOk()->assertContent('');
    });

    it('opens the modal with every product and the family filter', function () {
        $family = DB::table('ip_families')->insertGetId(['family_name' => 'Services']);
        $id = product(['product_name' => 'Widget', 'family_id' => $family, 'product_price' => 5]);

        $this->continueSession()->get('/products/ajax/modal_product_lookups/123', PRODUCT_AJAX)
            ->assertOk()
            ->assertSee('id="modal-choose-items"', false)
            ->assertSee('<option value="">Any family</option>', false)
            ->assertSee('<option value="'.$family.'"', false)
            ->assertSee('name="product_ids[]"', false)
            ->assertSee('value="'.$id.'"', false);
    });

    it('answers the table alone to a filter or a reset, narrowed by family and keyword', function () {
        $family = DB::table('ip_families')->insertGetId(['family_name' => 'Services']);
        product(['product_name' => 'Widget', 'family_id' => $family]);
        product(['product_name' => 'Gadget', 'product_description' => 'has 50% off']);

        $byFamily = $this->continueSession()->get("/products/ajax/modal_product_lookups/1?filter_family=$family", PRODUCT_AJAX);
        $byFamily->assertSee('Widget')->assertDontSee('Gadget')->assertDontSee('modal-choose-items');

        $this->continueSession()->get('/products/ajax/modal_product_lookups/1?filter_product=50%25', PRODUCT_AJAX)
            ->assertSee('Gadget')->assertDontSee('Widget');
        $this->continueSession()->get('/products/ajax/modal_product_lookups/1?reset_table=true', PRODUCT_AJAX)
            ->assertSee('Gadget')->assertSee('Widget')->assertDontSee('modal-choose-items');
    });

    it('reproduces the default_item_tax_rate quirk: true when the setting is set, 0 when it is not', function () {
        product();

        $this->continueSession()->get('/products/ajax/modal_product_lookups', PRODUCT_AJAX)
            ->assertSee("items[key].tax_rate_id = '0';", false);

        DB::table('ip_settings')->insert(['setting_key' => 'default_item_tax_rate', 'setting_value' => '3']);
        $this->continueSession()->get('/products/ajax/modal_product_lookups', PRODUCT_AJAX)
            ->assertSee("items[key].tax_rate_id = '1';", false);
    });
});

describe('process_product_selections', function () {
    beforeEach(fn () => productLogin($this));

    it('returns the chosen products as text/html JSON with every value a string and the price formatted', function () {
        $lookups = productLookups();
        $first = product(['product_name' => 'First', 'family_id' => $lookups['family'], 'unit_id' => $lookups['unit'], 'tax_rate_id' => $lookups['tax'], 'product_price' => 1234.5]);
        $second = product(['product_name' => 'Second']);
        product(['product_name' => 'Third']);

        $response = $this->continueSession()->post('/products/ajax/process_product_selections', ['product_ids' => [$first, $second]], PRODUCT_AJAX)->assertOk();

        expect($response->headers->get('Content-Type'))->toBe('text/html; charset=UTF-8');
        $rows = json_decode($response->getContent(), true);
        // Listed by family name: the product without a family comes first.
        expect($rows)->toHaveCount(2)
            ->and($rows[1])->toMatchArray([
                'product_id' => (string) $first, 'product_name' => 'First', 'product_price' => '1,234.50',
                'family_name' => 'Services', 'unit_name' => 'hour', 'unit_name_plrl' => 'hours',
                'tax_rate_name' => 'VAT', 'tax_rate_percent' => '20.00', 'family_id' => (string) $lookups['family'],
            ])
            ->and($rows[0])->toMatchArray(['product_name' => 'Second', 'family_id' => null, 'tax_rate_id' => null, 'product_price' => '10.00']);
        // The joined tables' columns follow the product's (SELECT *), the ids named by several of them keep their first position.
        expect(array_keys($rows[1]))->toBe([
            'product_id', 'family_id', 'product_sku', 'product_name', 'product_description', 'product_price', 'purchase_price',
            'provider_name', 'tax_rate_id', 'unit_id', 'product_tariff', 'family_name', 'unit_name', 'unit_name_plrl',
            'tax_rate_name', 'tax_rate_percent',
        ]);
    });

    it('answers nothing to a request that is not AJAX', function () {
        $this->continueSession()->post('/products/ajax/process_product_selections', ['product_ids' => [1]])->assertOk()->assertContent('');
    });
});

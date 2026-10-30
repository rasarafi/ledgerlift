<?php

use Tests\Parity\Support\AppClient;
use Tests\Parity\Support\ParityDatabases;
use Tests\Parity\Support\TableText;

/*
 * Products and their lookup modal, driven the same way against legacy and ledgerlift. The
 * fixture has 2 families, 2 units, 3 tax rates and 6 products.
 */

const PRODUCT_CREATE = [
    'family_id' => '2', 'product_sku' => 'TRN-DAY', 'product_name' => 'Training "day"', 'product_description' => "On site\nfor <b>teams</b> & more",
    'product_price' => '1234.5', 'unit_id' => '2', 'tax_rate_id' => '3', 'provider_name' => 'Quillstone & Co', 'purchase_price' => '800', 'product_tariff' => '7',
];

const PRODUCT_EDIT = [
    'family_id' => '1', 'product_sku' => 'TRN-HRS', 'product_name' => 'Training <i>hour</i>', 'product_description' => 'Hourly',
    'product_price' => '99.99', 'unit_id' => '1', 'tax_rate_id' => '0', 'provider_name' => '', 'purchase_price' => '', 'product_tariff' => '',
];

const PRODUCT_INPUTS = ['product_sku', 'product_name', 'product_price', 'provider_name', 'purchase_price', 'product_tariff'];

/** What the products list shows that a user reads: heading, flash messages, validation errors, table, pager. */
function productPage(AppClient $app, string $path = 'products'): array
{
    $response = $app->get($path);

    return [
        'status' => $response->status(),
        'location' => $app->location(),
        'title' => $app->texts('//h1[@class="headerbar-title"]'),
        'flash' => $app->texts('//div[contains(@class, "alert-dismissible")]/span'),
        'errors' => $app->texts('//div[@class="alert alert-danger"]'),
        'search box' => $app->texts('//input[@id="filter"]/@placeholder'),
        'rows' => TableText::rows($response->body()),
        'links' => array_map(
            fn (string $href) => str_replace(rtrim($app->baseUrl, '/').'/', '', $href),
            $app->texts('//table//a/@href'),
        ),
        'pager' => array_map(
            fn (string $href) => str_replace(rtrim($app->baseUrl, '/').'/', '', $href),
            $app->texts('//div[contains(@class, "model-pager")]//a/@href'),
        ),
    ];
}

/** The form of the last response as the user sees it: heading, inputs, text area and the select boxes. */
function productForm(AppClient $app): array
{
    $selects = [];

    foreach (['family_id', 'unit_id', 'tax_rate_id'] as $select) {
        $selects[$select] = [
            'options' => $app->texts("//select[@id='$select']/option"),
            'values' => $app->texts("//select[@id='$select']/option/@value"),
            'selected' => $app->texts("//select[@id='$select']/option[@selected]/@value"),
        ];
    }

    return [
        'panel' => $app->texts('(//div[@class="panel-heading"])[1]'),
        'panel titles' => $app->texts('//div[@class="panel-heading"]'),
        'inputs' => array_combine(PRODUCT_INPUTS, array_map(fn (string $name) => $app->text("//input[@name='$name']/@value"), PRODUCT_INPUTS)),
        'description' => $app->text('//textarea[@name="product_description"]'),
        'currency addons' => $app->texts('//span[@class="input-group-addon"]'),
        'selects' => $selects,
    ];
}

/** @param  array<string, string>  $data */
function productSubmit(AppClient $app, string $path, array $data, array $buttons = ['btn_submit' => '1']): array
{
    $response = $app->post($path, $data + $buttons);

    return [
        'status' => $response->status(),
        'location' => $app->location(),
        'errors' => $app->texts('//div[@class="alert alert-danger"]'),
        'form' => $response->redirect() ? null : productForm($app),
    ];
}

function productId(string $side): int
{
    return (int) ParityDatabases::connection($side)->table('ip_products')->max('product_id');
}

/** The stored product rows, to compare with the other side's. */
function productRows(string $side): array
{
    return ParityDatabases::connection($side)->table('ip_products')->orderBy('product_id')->get()->map(fn ($row) => (array) $row)->all();
}

it('creates, edits, rejects invalid saves, cancels and deletes a product', function () {
    $deleted = [];

    $result = parity(function (AppClient $app, string $side) use (&$deleted) {
        $app->login();
        $steps = ['start' => productPage($app)];

        $app->get('products/form');
        $steps['new form'] = productForm($app);

        $steps['create'] = productSubmit($app, 'products/form', PRODUCT_CREATE);
        $steps['after create'] = productPage($app);
        $id = productId($side);
        $steps['stored after create'] = productRows($side)[$id - 1] ?? null;

        $app->get("products/form/$id");
        $steps['edit form'] = productForm($app);
        $steps['edit'] = productSubmit($app, "products/form/$id", PRODUCT_EDIT);
        $steps['after edit'] = productPage($app);

        // Only the CSRF token posted: it is consumed before the controller, nothing is left to save.
        $tokenOnly = $app->post("products/form/$id");
        $steps['token-only post'] = ['status' => $tokenOnly->status(), 'errors' => $app->texts('//div[@class="alert alert-danger"]'), 'form' => productForm($app)];

        $invalid = ['product_name' => '', 'product_price' => '', 'family_id' => 'abc', 'unit_id' => '1x', 'tax_rate_id' => '2', 'product_description' => 'kept'];
        $steps['invalid'] = productSubmit($app, 'products/form', $invalid);
        $steps['invalid on edit'] = productSubmit($app, "products/form/$id", $invalid);
        $steps['whitespace only'] = productSubmit($app, 'products/form', ['product_name' => ' ', 'product_price' => ' ', 'family_id' => ' ']);
        $steps['numeric edge cases'] = productSubmit($app, 'products/form', ['product_name' => 'N', 'product_price' => '1', 'family_id' => '1e3', 'unit_id' => '-1', 'tax_rate_id' => '.5']);
        $steps['after invalid'] = productPage($app);

        $steps['cancel'] = productSubmit($app, "products/form/$id", PRODUCT_EDIT, ['btn_cancel' => '1']);
        $steps['missing record'] = productPage($app, 'products/form/9999');
        $steps['update of a missing id'] = productSubmit($app, 'products/form/9999', PRODUCT_EDIT);
        $steps['edit post without the save button'] = productSubmit($app, "products/form/$id", $invalid, []);
        $steps['delete over GET'] = productPage($app, "products/delete/$id");

        // Legacy v1.7.2 refuses every delete (see known-differences.md): its answer is recorded
        // apart, and the model-level delete (Mdl_products::delete(): DELETE ... WHERE product_id = id,
        // no other cleanup) is applied to its database by SQL so the two databases end alike.
        $delete = productSubmit($app, "products/delete/$id", []);
        $deleted[$side] = [$delete['location'], productPage($app)['flash']];
        $steps['delete status'] = $delete['status'];

        if ($side === 'legacy') {
            ParityDatabases::connection($side)->table('ip_products')->where('product_id', $id)->delete();
        }

        $steps['after delete'] = productPage($app);
        unset($steps['after delete']['flash']);

        return $steps;
    });

    expect($result->legacy['create'])->toMatchArray(['status' => 303, 'location' => 'products'])
        ->and($result->legacy['after create']['flash'])->toBe(['Record successfully created'])
        ->and($result->legacy['after edit']['flash'])->toBe(['Record successfully updated'])
        ->and($result->legacy['invalid']['status'])->toBe(200)
        ->and($result->legacy['invalid']['errors'])->toContain('The Product name field is required.', 'The Price field is required.', 'The Family field must contain only numbers.', 'The Unit field must contain only numbers.')
        ->and($result->legacy['stored after create'])->toMatchArray(['family_id' => '2', 'unit_id' => '2', 'tax_rate_id' => '3', 'product_price' => '1234.50'])
        ->and($result->legacy['delete over GET']['status'])->toBe(404)
        ->and($result->legacy['after delete']['rows'])->toHaveCount(7)
        ->and($deleted)->toBe([
            'legacy' => ['products/index', ['invalid_request']],
            'new' => ['products', ['Record successfully deleted']],
        ]);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('nulls the lookups and prices that a post leaves out or sends as 0 or empty', function () {
    $result = parity(function (AppClient $app, string $side) {
        $app->login();
        $steps = [];

        $steps['edit with name and price only'] = productSubmit($app, 'products/form/1', ['product_name' => 'Only these', 'product_price' => '5']);
        $steps['zero price'] = productSubmit($app, 'products/form', ['product_name' => 'Zero', 'product_price' => '0', 'purchase_price' => '0', 'family_id' => '0', 'unit_id' => '0', 'tax_rate_id' => '0']);
        $steps['no description'] = productSubmit($app, 'products/form', ['product_name' => 'Bare', 'product_price' => '3']);
        $steps['unknown lookups'] = productSubmit($app, 'products/form', ['product_name' => 'Orphan', 'product_price' => '3', 'family_id' => '77', 'unit_id' => '88', 'tax_rate_id' => '99']);
        $steps['stored'] = productRows($side);
        $app->get('products/form/'.productId($side));
        $steps['orphan form'] = productForm($app);
        $steps['list'] = productPage($app);

        return $steps;
    });

    expect($result->legacy['stored'][0])->toMatchArray(['product_name' => 'Only these', 'family_id' => null, 'unit_id' => null, 'tax_rate_id' => null, 'purchase_price' => null]);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('stores prices through standardize_amount, under default settings and a decimal comma', function (array $settings, array $stored) {
    $result = parity(function (AppClient $app, string $side) {
        $app->login();
        $statuses = [];

        foreach (['1.234,56', '1234.56', '1 234,5', '99,9', '1.234.567', '7,5'] as $price) {
            $statuses[$price] = productSubmit($app, 'products/form', ['product_name' => "P $price", 'product_price' => $price, 'purchase_price' => $price])['status'];
        }

        $app->get('products/form/'.productId($side));
        $form = productForm($app);

        return [
            'statuses' => $statuses,
            'stored' => ParityDatabases::connection($side)->table('ip_products')->where('product_id', '>', 6)->orderBy('product_id')->pluck('product_price')->all(),
            'stored purchase' => ParityDatabases::connection($side)->table('ip_products')->where('product_id', '>', 6)->orderBy('product_id')->pluck('purchase_price')->all(),
            'list' => productPage($app),
            'last form' => $form,
        ];
    }, $settings);

    expect($result->legacy['stored'])->toBe($stored);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
})->with([
    'default settings' => [[], ['1.23', '1234.56', '1.00', '999.00', '1.23', '75.00']],
    'european separators' => [['decimal_point' => ',', 'thousands_separator' => '.'], ['1234.56', '1234.56', '1.00', '99.90', '1234.57', '7.50']],
    'space thousands, currency before' => [['decimal_point' => '.', 'thousands_separator' => ' ', 'currency_symbol_placement' => 'before', 'currency_symbol' => 'EUR '], ['1.23', '1234.56', '1234.00', '99.00', '1.23', '7.00']],
]);

it('shows the sumex tariff column and field when sumex is on', function () {
    $result = parity(function (AppClient $app) {
        $app->login();
        $steps = ['list' => productPage($app)];
        $app->get('products/form/1');
        $steps['form'] = productForm($app);
        $steps['create'] = productSubmit($app, 'products/form', PRODUCT_CREATE);
        $steps['list after'] = productPage($app);

        return $steps;
    }, ['sumex' => '1']);

    expect($result->legacy['list']['rows'][0])->toContain('Tariff');
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('pages by offset, with the limit of default_list_limit', function () {
    $result = parity(function (AppClient $app) {
        $app->login();

        return array_combine(
            ['0', '1', '2', '5', '6', '15'],
            array_map(fn (string $offset) => productPage($app, "products/index/$offset"), ['0', '1', '2', '5', '6', '15']),
        ) + ['bare' => productPage($app)];
    }, ['default_list_limit' => '2']);

    expect($result->legacy['0']['rows'])->toHaveCount(3)
        ->and($result->legacy['0']['pager'])->not->toBeEmpty();
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('filters the products list over AJAX', function () {
    $result = parity(function (AppClient $app) {
        $app->login();
        $app->post('products/form', ['product_name' => '100% Cotton', 'product_price' => '5', 'product_description' => 'Web_Design', 'btn_submit' => '1']);
        $index = productPage($app);

        $filtered = [];
        foreach (['host', 'HOSTING', 'hosting basic', 'srv', '95', '95.00', 'quillstone', 'in-house', 'e', 'e s', '0%', '%', '_', 'web_', ' ', '', '0', "'", 'managed  server'] as $query) {
            $response = $app->post('filter/ajax/filter_products', ['filter_query' => $query], ajax: true);
            $filtered[$query] = [$response->status(), TableText::rows('<html><body>'.$response->body().'</body></html>')];
        }

        $plain = $app->post('filter/ajax/filter_products', ['filter_query' => 'e']);

        return ['filtered' => $filtered, 'not ajax' => [$plain->status(), $plain->body()], 'index' => $index];
    });

    expect($result->legacy['filtered']['host'][1])->toHaveCount(1 + 3)
        ->and($result->legacy['filtered']['%'][1])->toHaveCount(1 + 1)
        ->and($result->legacy['not ajax'])->toBe([200, '']);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('stores markup in product fields as the XSS filter leaves it', function () {
    $result = parity(function (AppClient $app, string $side) {
        $app->login();
        $hostile = ['<script>alert(1)</script>', 'x"><img src=x onerror=alert(1)>', "Caf\u{e9} <a href=\"javascript:evil()\">link</a>", 'a < b > c', '%3Cscript%3E', '&lt;b&gt;', 'j a v a s c r i p t :'];
        $steps = [];

        foreach ($hostile as $n => $value) {
            $steps["product $n"] = productSubmit($app, 'products/form', ['product_name' => $value, 'product_description' => $value, 'product_sku' => $value, 'product_price' => '1']);
        }

        $steps['list'] = productPage($app);
        $steps['stored'] = productRows($side);

        return $steps;
    });

    expect($result->legacy['list']['rows'])->toHaveCount(1 + 6 + 7);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('sends a guest to the login page from every product screen', function () {
    $result = parity(function (AppClient $app) {
        $steps = [];

        foreach (['products', 'products/index/15', 'products/form', 'products/form/1'] as $path) {
            $steps["GET $path"] = [$app->get($path)->status(), $app->location()];
        }

        foreach (['products/ajax/modal_product_lookups/1', 'products/ajax/modal_product_lookups'] as $path) {
            $response = $app->get($path, [], ajax: true);
            $steps["AJAX GET $path"] = [$response->status(), $app->location(), $response->redirect() ? null : $response->body()];
            $response = $app->get($path);
            $steps["GET $path"] = [$response->status(), $app->location(), $response->redirect() ? null : $response->body()];
        }

        $response = $app->post('products/ajax/process_product_selections', ['product_ids' => [1]], ajax: true);
        $steps['AJAX POST process'] = [$response->status(), $app->location()];
        $response = $app->post('filter/ajax/filter_products', ['filter_query' => 'a'], ajax: true);
        $steps['AJAX POST filter'] = [$response->status(), $app->location()];

        return $steps;
    });

    expect($result->legacy['GET products'])->toBe([307, 'sessions/login'])
        ->and($result->legacy['GET products/ajax/modal_product_lookups'])->toBe([200, null, '']);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

/** What the lookup modal shows: product rows, the filter box and its family dropdown, the posted-to script. */
function productModal(AppClient $app, string $path, array $query = [], bool $ajax = true): array
{
    $response = $app->get($path, $query, ajax: $ajax);
    $base = rtrim($app->baseUrl, '/');
    $script = $app->text('//script') ?? '';

    return [
        'status' => $response->status(),
        'location' => $app->location(),
        'rows' => $response->body() === '' ? [] : TableText::rows($response->body(), '//table[@id="products_table"]'),
        'checkboxes' => $app->texts('//input[@name="product_ids[]"]/@value'),
        'modal' => $app->texts('//div[@id="modal-choose-items"]//h4'),
        'family options' => $app->texts('//select[@id="filter_family"]/option'),
        'family values' => $app->texts('//select[@id="filter_family"]/option/@value'),
        'family selected' => $app->texts('//select[@id="filter_family"]/option[@selected]/@value'),
        'filter value' => $app->texts('//input[@id="filter_product"]/@value'),
        'filter placeholder' => $app->texts('//input[@id="filter_product"]/@placeholder'),
        'buttons' => $app->texts('//button'),
        'default tax rate in script' => preg_match("/items\\[key\\]\\.tax_rate_id = '([^']*)';/", $script, $found) ? $found[1] : null,
        // The whole inline script, whitespace collapsed, the app's own URL replaced.
        'script' => trim((string) preg_replace('/\s+/u', ' ', str_replace($base, '{base}', $script))),
    ];
}

it('opens the product lookup modal, and answers its filter and reset requests with the table', function (array $settings) {
    $result = parity(function (AppClient $app) {
        $app->login();
        $steps = ['modal' => productModal($app, 'products/ajax/modal_product_lookups/123')];

        $steps['no random'] = productModal($app, 'products/ajax/modal_product_lookups');
        $steps['not ajax'] = productModal($app, 'products/ajax/modal_product_lookups/1', [], false);

        foreach ([
            'family 1' => ['filter_family' => '1'],
            'family 2' => ['filter_family' => '2'],
            'family 0' => ['filter_family' => '0'],
            'family none' => ['filter_family' => '99'],
            'family text' => ['filter_family' => 'abc'],
            'family 1abc' => ['filter_family' => '1abc'],
            'product host' => ['filter_product' => 'host'],
            'product sku' => ['filter_product' => 'srv-'],
            'product description' => ['filter_product' => 'billed monthly'],
            'product percent' => ['filter_product' => '%'],
            'product underscore' => ['filter_product' => '_'],
            'product quote' => ['filter_product' => "'"],
            'product bang' => ['filter_product' => '!'],
            'product zero' => ['filter_product' => '0'],
            'product markup' => ['filter_product' => '<script>alert(1)</script>'],
            'product quote char' => ['filter_product' => 'a"b'],
            'both' => ['filter_family' => '2', 'filter_product' => 'back'],
            'both no match' => ['filter_family' => '1', 'filter_product' => 'hosting'],
            'reset' => ['reset_table' => 'true'],
            'reset zero' => ['reset_table' => '0'],
            'reset with filters' => ['reset_table' => 'true', 'filter_family' => '2', 'filter_product' => 'managed'],
            'empty filters' => ['filter_family' => '', 'filter_product' => ''],
        ] as $name => $query) {
            $steps[$name] = productModal($app, 'products/ajax/modal_product_lookups/42', $query);
        }

        return $steps;
    }, $settings);

    expect($result->legacy['modal']['rows'])->toHaveCount(1 + 6)
        ->and($result->legacy['modal']['family options'])->toHaveCount(1 + 2)
        ->and($result->legacy['family 2']['rows'])->toHaveCount(1 + 3)
        ->and($result->legacy['reset']['modal'])->toBe([])
        ->and($result->legacy['not ajax']['rows'])->toBe([]);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
})->with([
    'default_item_tax_rate unset' => [[]],
    // Ajax.php:41-42: `$default_item_tax_rate !== '' ?: 0` gives true, not the setting.
    'default_item_tax_rate set' => [['default_item_tax_rate' => '3']],
    'european separators and currency before' => [['decimal_point' => ',', 'thousands_separator' => '.', 'currency_symbol_placement' => 'before']],
]);

it('shows the default_item_tax_rate quirk in the modal script: 1 when the setting is set, 0 when not', function (array $settings, string $expected) {
    $result = parity(function (AppClient $app) {
        $app->login();

        return ['script value' => productModal($app, 'products/ajax/modal_product_lookups/1')['default tax rate in script']];
    }, $settings);

    expect($result->legacy['script value'])->toBe($expected);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
})->with([
    'unset' => [[], '0'],
    'set to 3' => [['default_item_tax_rate' => '3'], '1'],
    'set to 0' => [['default_item_tax_rate' => '0'], '1'],
]);

it('answers process_product_selections with the same JSON', function (array $settings) {
    $result = parity(function (AppClient $app) {
        $app->login();
        $steps = [];

        foreach ([
            'products 1 and 2' => ['product_ids' => ['1', '2']],
            'reversed' => ['product_ids' => ['2', '1']],
            'one' => ['product_ids' => ['6']],
            'with a missing id' => ['product_ids' => ['3', '99']],
            'all missing' => ['product_ids' => ['99']],
            'repeated' => ['product_ids' => ['1', '1']],
            'text id' => ['product_ids' => ['abc']],
            'quote' => ['product_ids' => ["1' OR '1'='1"]],
        ] as $name => $data) {
            $response = $app->post('products/ajax/process_product_selections', $data, ajax: true);
            $steps[$name] = [
                'status' => $response->status(),
                'content type' => $response->header('Content-Type'),
                'decoded' => json_decode($response->body(), true),
            ];
        }

        $plain = $app->post('products/ajax/process_product_selections', ['product_ids' => ['1']]);
        $steps['not ajax'] = [$plain->status(), $plain->body()];

        return $steps;
    }, $settings);

    $first = $result->legacy['products 1 and 2']['decoded'];
    expect($first)->toHaveCount(2)
        ->and($first[0])->toMatchArray(['product_id' => '2', 'product_price' => $settings === [] ? '120.00' : '120,00', 'family_name' => 'Services'])
        ->and($result->legacy['products 1 and 2']['content type'])->toStartWith('text/html')
        ->and($result->legacy['not ajax'])->toBe([200, '']);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
})->with([
    'default settings' => [[]],
    'european separators' => [['decimal_point' => ',', 'thousands_separator' => '.']],
]);

it('answers process_product_selections the same for a post without product ids', function () {
    $result = parity(function (AppClient $app) {
        $app->login();
        $none = $app->post('products/ajax/process_product_selections', [], ajax: true);
        $scalar = $app->post('products/ajax/process_product_selections', ['product_ids' => '1'], ajax: true);

        return ['none' => $none->status(), 'scalar' => $scalar->status()];
    });

    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

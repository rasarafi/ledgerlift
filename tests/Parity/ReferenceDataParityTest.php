<?php

use Tests\Parity\Support\AppClient;
use Tests\Parity\Support\ParityDatabases;
use Tests\Parity\Support\TableText;

/*
 * The five reference-data modules, driven the same way against legacy and ledgerlift. A scenario
 * returns what it saw (statuses, redirects, flash and validation texts, the index table, the
 * form's input values); both apps must return the same, and leave the same rows behind.
 */

const REFERENCE_PARITY = [
    'tax_rates' => [
        'table' => 'ip_tax_rates', 'key' => 'tax_rate_id', 'existing' => 1,
        'create' => ['tax_rate_name' => 'Luxury', 'tax_rate_percent' => '7,5'],
        'edit' => ['tax_rate_name' => 'Luxury "goods" & <b>more</b>', 'tax_rate_percent' => '12.5'],
        'invalid' => ['tax_rate_name' => '', 'tax_rate_percent' => '9'],
        'inputs' => ['tax_rate_name', 'tax_rate_percent'],
    ],
    'families' => [
        'table' => 'ip_families', 'key' => 'family_id', 'existing' => 1,
        'create' => ['is_update' => '0', 'family_name' => 'Training'],
        'edit' => ['is_update' => '1', 'family_name' => 'Training "&" <b>Coaching</b>'],
        'invalid' => ['is_update' => '0', 'family_name' => ''],
        'inputs' => ['is_update', 'family_name'],
    ],
    'units' => [
        'table' => 'ip_units', 'key' => 'unit_id', 'existing' => 1,
        'create' => ['is_update' => '0', 'unit_name' => 'day', 'unit_name_plrl' => 'days'],
        'edit' => ['is_update' => '1', 'unit_name' => 'work "day"', 'unit_name_plrl' => '<i>work</i> days & nights'],
        'invalid' => ['is_update' => '0', 'unit_name' => 'week', 'unit_name_plrl' => ''],
        'inputs' => ['is_update', 'unit_name', 'unit_name_plrl'],
    ],
    'payment_methods' => [
        'table' => 'ip_payment_methods', 'key' => 'payment_method_id', 'existing' => 3,
        'create' => ['is_update' => '0', 'payment_method_name' => 'Courier'],
        'edit' => ['is_update' => '1', 'payment_method_name' => 'Courier on "delivery" & <u>pickup</u>'],
        'invalid' => ['is_update' => '0', 'payment_method_name' => ''],
        'inputs' => ['is_update', 'payment_method_name'],
    ],
    'invoice_groups' => [
        'table' => 'ip_invoice_groups', 'key' => 'invoice_group_id', 'existing' => 5,
        'create' => ['invoice_group_name' => 'Pro forma', 'invoice_group_identifier_format' => 'PF-{{{yy}}}{{{month}}}-{{{id}}}', 'invoice_group_next_id' => '12', 'invoice_group_left_pad' => '3'],
        'edit' => ['invoice_group_name' => 'Pro "forma" & <b>more</b>', 'invoice_group_identifier_format' => '{{{year}}}/{{{day}}}/{{{id}}}', 'invoice_group_next_id' => '40', 'invoice_group_left_pad' => '5'],
        'invalid' => ['invoice_group_name' => 'Nameless', 'invoice_group_identifier_format' => '', 'invoice_group_next_id' => '9', 'invoice_group_left_pad' => '2'],
        'inputs' => ['invoice_group_name', 'invoice_group_identifier_format', 'invoice_group_next_id', 'invoice_group_left_pad'],
    ],
];

/** What the page shows that a user reads: heading, flash messages, validation errors, list. */
function referencePage(AppClient $app, string $path): array
{
    $response = $app->get($path);

    return [
        'status' => $response->status(),
        'location' => $app->location(),
        'title' => $app->texts('//h1[@class="headerbar-title"]'),
        'flash' => $app->texts('//div[contains(@class, "alert-dismissible")]/span'),
        'errors' => $app->texts('//div[@class="alert alert-danger"]'),
        'rows' => TableText::rows($response->body()),
        'pager' => array_map(
            fn (string $href) => str_replace(rtrim($app->baseUrl, '/').'/', '', $href),
            $app->texts('//div[contains(@class, "model-pager")]//a/@href'),
        ),
        'pager_disabled' => $app->texts('//div[contains(@class, "model-pager")]//a[contains(@class, "disabled")]/@title'),
    ];
}

/** @param  list<string>  $names */
function referenceInputs(AppClient $app, array $names): array
{
    return array_combine($names, array_map(fn (string $name) => $app->text("//input[@name='$name']/@value"), $names));
}

function referenceSubmit(AppClient $app, string $path, array $data, array $buttons = ['btn_submit' => '1']): array
{
    $response = $app->post($path, $data + $buttons);

    return [
        'status' => $response->status(),
        'location' => $app->location(),
        'errors' => $app->texts('//div[@class="alert alert-danger"]'),
        'inputs' => $response->redirect() ? null : array_map(
            fn (string $name) => $app->text("//input[@name='$name']/@value"),
            array_combine(array_keys($data), array_keys($data)),
        ),
    ];
}

function referenceId(string $side, array $module): int
{
    return (int) ParityDatabases::connection($side)->table($module['table'])->max($module['key']);
}

it('creates, edits, rejects an invalid save, cancels and deletes', function (string $name) {
    $module = REFERENCE_PARITY[$name];
    $deleted = [];

    $result = parity(function (AppClient $app, string $side) use ($name, $module, &$deleted) {
        $app->login();
        $steps = ['start' => referencePage($app, $name)];

        $steps['new form'] = referencePage($app, "$name/form");
        $steps['new form inputs'] = referenceInputs($app, $module['inputs']);

        $steps['create'] = referenceSubmit($app, "$name/form", $module['create']);
        $steps['after create'] = referencePage($app, $name);
        $id = referenceId($side, $module);

        $steps['edit form'] = referencePage($app, "$name/form/$id");
        $steps['edit form inputs'] = referenceInputs($app, $module['inputs']);
        $steps['edit'] = referenceSubmit($app, "$name/form/$id", $module['edit']);
        $steps['after edit'] = referencePage($app, $name);

        $steps['invalid'] = referenceSubmit($app, "$name/form", $module['invalid']);
        $steps['invalid on edit'] = referenceSubmit($app, "$name/form/$id", $module['invalid']);
        $steps['after invalid'] = referencePage($app, $name);

        $steps['cancel'] = referenceSubmit($app, "$name/form/$id", $module['edit'], ['btn_cancel' => '1']);
        $steps['missing record'] = referencePage($app, "$name/form/9999");
        $steps['update of a missing id'] = referenceSubmit($app, "$name/form/9999", $module['edit']);
        $steps['edit post without the save button'] = referenceSubmit($app, "$name/form/$id", $module['invalid'], []);

        $steps['delete over GET'] = referencePage($app, "$name/delete/$id");

        // Legacy v1.7.2 refuses every delete (see known-differences.md): its answer is recorded
        // apart, and its row is removed by hand so the two databases end alike.
        $delete = referenceSubmit($app, "$name/delete/$id", []);
        $deleted[$side] = [$delete['location'], referencePage($app, $name)['flash']];
        $steps['delete status'] = $delete['status'];

        if ($side === 'legacy') {
            ParityDatabases::connection($side)->table($module['table'])->where($module['key'], $id)->delete();
        }

        $steps['after delete'] = referencePage($app, $name);
        unset($steps['after delete']['flash']);

        return $steps;
    });

    // The scenario really did create, edit and delete on legacy before the two are compared.
    expect($result->legacy['create'])->toMatchArray(['status' => 303, 'location' => $name])
        ->and($result->legacy['after create']['flash'])->toBe(['Record successfully created'])
        ->and($result->legacy['after edit']['flash'])->toBe(['Record successfully updated'])
        ->and($result->legacy['invalid']['status'])->toBe(200)
        ->and($result->legacy['invalid']['errors'])->not->toBeEmpty()
        ->and($result->legacy['delete over GET']['status'])->toBe(404)
        ->and($result->legacy['after delete']['rows'])->toBe($result->legacy['start']['rows'])
        ->and($deleted)->toBe([
            'legacy' => [$name === 'families' ? 'families' : "$name/index", ['invalid_request']],
            'new' => [$name, ['Record successfully deleted']],
        ]);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
})->with(array_keys(REFERENCE_PARITY));

it('stores the percent 7,5 of a tax rate the way legacy does, under default settings and a decimal comma', function (array $settings, array $stored) {
    $result = parity(function (AppClient $app, string $side) {
        $app->login();
        $app->get('tax_rates/form');
        $post = $app->post('tax_rates/form', ['tax_rate_name' => 'Comma', 'tax_rate_percent' => '7,5', 'btn_submit' => '1']);
        $id = (int) ParityDatabases::connection($side)->table('ip_tax_rates')->max('tax_rate_id');
        $thousands = $app->post('tax_rates/form', ['tax_rate_name' => 'Thousands', 'tax_rate_percent' => '1.234,5', 'btn_submit' => '1']);

        return [
            'statuses' => [$post->status(), $thousands->status()],
            'stored' => ParityDatabases::connection($side)->table('ip_tax_rates')->where('tax_rate_id', '>=', $id)->orderBy('tax_rate_id')->pluck('tax_rate_percent')->all(),
            'list' => referencePage($app, 'tax_rates'),
            'form' => (function () use ($app, $id) {
                $app->get("tax_rates/form/$id");

                return referenceInputs($app, ['tax_rate_name', 'tax_rate_percent']);
            })(),
        ];
    }, $settings);

    // Legacy's standardize_amount() strips the thousands separator and the column (decimal(5,2))
    // cuts or clamps the rest: "7,5" is 75 under default settings.
    expect($result->legacy['statuses'])->toBe([303, 303])
        ->and($result->legacy['stored'])->toBe($stored);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
})->with([
    'default settings' => [[], ['75.00', '1.23']],
    'decimal comma' => [['decimal_point' => ',', 'thousands_separator' => '.'], ['7.50', '999.99']],
    'space thousands, three decimals' => [['decimal_point' => '.', 'thousands_separator' => ' ', 'tax_rate_decimal_places' => '3'], ['7.00', '1.23']],
]);

it('pages by offset, with the limit of default_list_limit', function (string $name) {
    $result = parity(function (AppClient $app) use ($name) {
        $app->login();

        return array_combine(
            ['0', '1', '2', '3', '5', '15'],
            array_map(fn (string $offset) => referencePage($app, "$name/index/$offset"), ['0', '1', '2', '3', '5', '15']),
        ) + ['bare' => referencePage($app, $name)];
    }, ['default_list_limit' => '1']);

    expect($result->legacy['0']['rows'])->toHaveCount(2)
        ->and($result->legacy['0']['pager'])->not->toBeEmpty();
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
})->with(array_keys(REFERENCE_PARITY));

it('refuses a new family, unit or payment method whose name exists and keeps the edit of its own', function (string $name, array $existing, array $repeat) {
    $result = parity(function (AppClient $app, string $side) use ($name, $existing, $repeat) {
        $app->login();
        $steps = ['create' => referenceSubmit($app, "$name/form", $existing)];
        $steps['repeat'] = referenceSubmit($app, "$name/form", $repeat);
        $steps['form after repeat'] = referencePage($app, "$name/form");
        $steps['list'] = referencePage($app, $name);

        $module = REFERENCE_PARITY[$name];
        $id = referenceId($side, $module);
        $steps['edit keeps its name'] = referenceSubmit($app, "$name/form/$id", ['is_update' => '1'] + $existing);
        $steps['without is_update'] = referenceSubmit($app, "$name/form", array_diff_key($repeat, ['is_update' => 1]));
        $steps['final'] = referencePage($app, $name);

        return $steps;
    });

    expect($result->legacy['repeat']['location'])->toBe("$name/form")
        ->and($result->legacy['form after repeat']['flash'])->toHaveCount(1);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
})->with([
    'families' => ['families', ['is_update' => '0', 'family_name' => 'Training'], ['is_update' => '0', 'family_name' => 'TRAINING']],
    'units' => ['units', ['is_update' => '0', 'unit_name' => 'day', 'unit_name_plrl' => 'days'], ['is_update' => '0', 'unit_name' => 'Day', 'unit_name_plrl' => 'dayz']],
    'payment methods' => ['payment_methods', ['is_update' => '0', 'payment_method_name' => 'Courier'], ['is_update' => '0', 'payment_method_name' => 'courier ']],
]);

it('shows the defaults of a new invoice group again after a failed save, and keeps posted values on an edit', function () {
    $result = parity(function (AppClient $app) {
        $app->login();
        $failed = ['invoice_group_name' => '', 'invoice_group_identifier_format' => 'X-{{{id}}}', 'invoice_group_next_id' => '9', 'invoice_group_left_pad' => '5'];

        $new = referenceSubmit($app, 'invoice_groups/form', $failed);
        $onEdit = referenceSubmit($app, 'invoice_groups/form/5', $failed);

        return ['new' => $new, 'on edit' => $onEdit];
    });

    expect($result->legacy['new']['inputs'])->toMatchArray(['invoice_group_next_id' => '1', 'invoice_group_left_pad' => '0'])
        ->and($result->legacy['on edit']['inputs'])->toMatchArray(['invoice_group_next_id' => '9', 'invoice_group_left_pad' => '5']);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('filters the families list over AJAX', function () {
    $result = parity(function (AppClient $app) {
        $app->login();
        $app->post('families/form', ['is_update' => '0', 'family_name' => '100% Cotton', 'btn_submit' => '1']);
        $app->post('families/form', ['is_update' => '0', 'family_name' => 'Web_Design', 'btn_submit' => '1']);
        $index = referencePage($app, 'families');

        $filtered = [];
        foreach (['host', 'HOSTING', 'e', 'e s', '0%', '%', '_', 'web_', ' ', '', '0', "'"] as $query) {
            $response = $app->post('filter/ajax/filter_families', ['filter_query' => $query], ajax: true);
            $filtered[$query] = [$response->status(), TableText::rows('<html><body>'.$response->body().'</body></html>')];
        }

        $plain = $app->post('filter/ajax/filter_families', ['filter_query' => 'e']);

        return [
            'search box' => [$app->texts('//input[@id="filter"]/@placeholder')],
            'filtered' => $filtered,
            'not ajax' => [$plain->status(), $plain->body()],
            'index' => $index,
        ];
    });

    expect($result->legacy['filtered']['host'][1])->toHaveCount(2)
        ->and($result->legacy['filtered']['%'][1])->toHaveCount(2)
        ->and($result->legacy['not ajax'])->toBe([200, '']);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('stores markup in names as the XSS filter leaves it', function () {
    $result = parity(function (AppClient $app) {
        $app->login();
        $hostile = ['<script>alert(1)</script>', 'x"><img src=x onerror=alert(1)>', "Caf\u{e9} <a href=\"javascript:evil()\">link</a>", 'a < b > c', '%3Cscript%3E', '&lt;b&gt;', 'j a v a s c r i p t :'];
        $steps = [];

        foreach ($hostile as $n => $value) {
            $steps["family $n"] = referenceSubmit($app, 'families/form', ['is_update' => '0', 'family_name' => $value]);
        }

        $steps['list'] = referencePage($app, 'families');

        return $steps;
    });

    // Header + the 2 fixture families + 6 of the 7 values: the last one is cleaned to the
    // name of the first ("[removed]") and refused as a duplicate.
    expect($result->legacy['list']['rows'])->toHaveCount(1 + 2 + 6)
        ->and($result->legacy['family 6']['location'])->toBe('families/form');
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
});

it('sends a guest to the login page from every screen', function (string $name) {
    $result = parity(function (AppClient $app) use ($name) {
        $steps = [];

        foreach (["$name", "$name/index/15", "$name/form", "$name/form/1"] as $path) {
            $steps["GET $path"] = [$app->get($path)->status(), $app->location()];
        }

        return $steps;
    });

    expect($result->legacy['GET '.$name])->toBe([307, 'sessions/login']);
    expect($result)->toMatchAcrossApps()->toHaveNoDifferences();
})->with(array_keys(REFERENCE_PARITY));

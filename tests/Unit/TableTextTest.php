<?php

use Tests\Parity\Support\TableText;

it('reads the cell texts of the first table, collapsing whitespace only', function () {
    $html = '<html><body><p>x</p><table><thead><tr><th> Name </th><th>Options</th></tr></thead>
        <tbody><tr><td>
            Tom   &amp;
            Jerry</td><td><a>Edit</a>  <b>Delete</b></td></tr><tr><td>7,5%</td><td></td></tr></tbody></table><table><tr><td>other</td></tr></table></body></html>';

    expect(TableText::rows($html))->toBe([
        ['Name', 'Options'],
        ['Tom & Jerry', 'Edit Delete'],
        ['7,5%', ''],
    ])->and(TableText::rows($html, '//table[2]'))->toBe([['other']])
        ->and(TableText::rows('<p>no table</p>'))->toBe([]);
});

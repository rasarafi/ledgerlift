<?php

/*
 * The rounding sweep behind App\Support\LegacyMath: 200,000 discount-style values
 * (total / 100 * percent, seeded), each rounded and formatted at 2 or 3 places. Prints
 * a hash of what round() and number_format() give on this PHP and, where ledgerlift's
 * autoloader is present, of what LegacyMath gives and on how many values they differ.
 *
 *   docker compose exec -T legacy php < scripts/rounding-sweep.php   # PHP 8.2
 *   docker compose exec -T new php < scripts/rounding-sweep.php      # PHP 8.4 and LegacyMath
 *
 * LegacyMath's hashes on PHP 8.4 equal the plain ones on PHP 8.2 when it reproduces 8.2.
 */

use App\Support\LegacyMath;

$autoload = getcwd().'/vendor/autoload.php';

if (is_file($autoload)) {
    require $autoload;
}

$legacyMath = class_exists(LegacyMath::class);

mt_srand(42);

$plain = ['round' => hash_init('sha256'), 'number_format' => hash_init('sha256')];
$emulated = ['round' => hash_init('sha256'), 'number_format' => hash_init('sha256')];
$differs = ['round' => 0, 'number_format' => 0];
$count = 200000;

for ($i = 0; $i < $count; $i++) {
    $value = (mt_rand(1, 50000000) / 100) / 100 * (mt_rand(1, 10000) / 100);
    $places = mt_rand(0, 1) ? 2 : 3;

    $results = [
        'round' => [var_export(round($value, $places), true)],
        'number_format' => [number_format($value, $places, '.', '')],
    ];

    if ($legacyMath) {
        $results['round'][] = var_export(LegacyMath::round($value, $places), true);
        $results['number_format'][] = LegacyMath::numberFormat($value, $places, '.', '');
    }

    foreach ($results as $function => $result) {
        hash_update($plain[$function], $result[0]."\n");

        if ($legacyMath) {
            hash_update($emulated[$function], $result[1]."\n");
            $differs[$function] += (int) ($result[0] !== $result[1]);
        }
    }
}

echo 'PHP '.PHP_VERSION.", $count values, seed 42, each at 2 or 3 places\n";

foreach (['round', 'number_format'] as $function) {
    printf("%-27s %s\n", "$function()", hash_final($plain[$function]));
}

if ($legacyMath) {
    foreach (['round' => 'round', 'number_format' => 'numberFormat'] as $function => $method) {
        printf("%-27s %s  (%s() differs on %d values)\n", "LegacyMath::$method()", hash_final($emulated[$function]), $function, $differs[$function]);
    }
}

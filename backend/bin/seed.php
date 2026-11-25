<?php

// Sample users for local development:  php bin/seed.php

declare(strict_types=1);

require __DIR__ . '/_boot.php';

$users = new Ancc\Users(Ancc\Db::connect(ancc_config()));
$sample = [
    ['cs1240001', 'Aarav Sharma', 'Girnar', [], '+91 98100 00001'],
    ['ee1250002', 'Diya Patel', 'Himadri', [], '+91 98100 00002'],
    ['me1260003', 'Kabir Singh', null, [], null],
    ['cs5230004', 'Ishita Rao', 'Kailash', ['coordinator'], '+91 98100 00004'],
    ['mt1240005', 'Rohan Mehta', 'Satpura', ['icpc_admin'], '+91 98100 00005'],
    ['ch1230006', 'Ananya Gupta', 'Nilgiri', ['finance'], '+91 98100 00006'],
    ['cs1230007', 'Vihaan Verma', 'Shivalik', ['super_admin'], '+91 98100 00007'],
];
foreach ($sample as [$kid, $name, $hostel, $roles, $phone]) {
    $u = $users->upsertFromIdentity(new Ancc\Auth\Identity($kid, $name, "$kid@iitd.ac.in"));
    $users->update($u['id'], [
        'hostel' => $hostel,
        'phone' => $phone,
        'codeforces' => strtolower(explode(' ', $name)[0]) . '_cf',
    ]);
    $users->addRoles($u['id'], $roles);
}
echo 'Seeded ', count($sample), " users.\n";

// Each feature's sample data: src/Features/*/seed.php (folders starting with "_" are skipped).
$db = Ancc\Db::connect(ancc_config());
$seeds = glob(dirname(__DIR__) . '/src/Features/*/seed.php') ?: [];
sort($seeds);
foreach ($seeds as $seed) {
    if (str_starts_with(basename(dirname($seed)), '_')) {
        continue;
    }
    require $seed;
    echo 'Seeded ', basename(dirname($seed)), ".\n";
}

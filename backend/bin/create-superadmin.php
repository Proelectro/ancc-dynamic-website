<?php

// Give an existing user super_admin (use once in production after they log in with IITD):
//   php bin/create-superadmin.php cs1230007

declare(strict_types=1);

require __DIR__ . '/_boot.php';

if (count($argv) !== 2) {
    fwrite(STDERR, "Usage: php bin/create-superadmin.php <kerberos_id>\n");
    exit(1);
}
$users = new Ancc\Users(Ancc\Db::connect(ancc_config()));
$user = $users->findByKerberos($argv[1]);
if ($user === null) {
    fwrite(STDERR, "No user {$argv[1]}. Ask them to log in with IITD once first.\n");
    exit(1);
}
$users->addRoles($user['id'], ['super_admin']);
echo "{$argv[1]} is now super_admin.\n";

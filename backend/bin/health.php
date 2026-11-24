<?php

// Run the API's health check once, in-process (used by the deploy script):  php bin/health.php

declare(strict_types=1);

require __DIR__ . '/_boot.php';

$app = new Ancc\App(ancc_config());
$response = $app->handle(new Ancc\Http\Request('GET', '/api/v1/health'));
echo $response->body, "\n";
exit(($response->data()['status'] ?? '') === 'ok' ? 0 : 1);

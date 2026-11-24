<?php

declare(strict_types=1);

// Shared start-up for command-line scripts. Uses the same config as the web app.
require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

function ancc_config(): Ancc\Config
{
    return Ancc\Config::load();
}

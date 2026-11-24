<?php

// Local development entry point:  php -S localhost:8000 -t public public/index.php
// (On the IITD server, html/new/api.php plays this role; see deploy/iitd/.)

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

Ancc\App::main();

<?php

// Copy to config.php (git-ignored) and adjust. Every key is optional; see src/Config.php for defaults.
// On the IITD server, deploy/iitd/deploy.sh setup writes /var/www/ancc/https/app/config.php for you.

return [
    'ENV' => 'development',
    'AUTH_MODE' => 'dev',

    'DB_HOST' => 'localhost', // 'localhost' = local socket; use '127.0.0.1' for TCP
    'DB_PORT' => 3306,
    'DB_NAME' => 'ancc',
    'DB_USER' => 'ancc',
    'DB_PASSWORD' => 'ancc',

    'FRONTEND_URL' => 'http://localhost:5173',
    'JWT_SECRET' => 'dev-only-secret-not-for-production',
];

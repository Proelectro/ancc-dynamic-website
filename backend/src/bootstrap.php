<?php

declare(strict_types=1);

/*
 * Loads the app. No Composer is needed at runtime: classes under the Ancc\ namespace are
 * autoloaded from src/. (Composer is only used locally, for PHPUnit.)
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'Ancc\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

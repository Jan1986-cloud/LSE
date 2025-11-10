<?php

declare(strict_types=1);

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require $autoloadPath;
} else {
    foreach ([
        dirname(__DIR__) . '/src/UserAuthService.php',
        dirname(__DIR__) . '/src/BillingService.php',
        dirname(__DIR__) . '/src/AuthGuard.php',
        dirname(__DIR__) . '/src/Exceptions/UnauthorizedException.php',
        dirname(__DIR__) . '/src/Exceptions/ForbiddenException.php',
    ] as $file) {
        require_once $file;
    }
}

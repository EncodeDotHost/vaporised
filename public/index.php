<?php

declare(strict_types=1);

use Vaporised\Config;
use Vaporised\Controller;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// PHP built-in server (local development only): let it serve static files.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . $path)) {
    return false;
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require dirname(__DIR__) . '/src/bootstrap.php';

header_remove('X-Powered-By');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
if (($_SERVER['HTTPS'] ?? 'off') !== 'off') {
    header('Strict-Transport-Security: max-age=31536000');
}

set_exception_handler(static function (Throwable $e): void {
    error_log('vaporised: ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'An error occurred. Try again later.';
});

$controller = new Controller(Config::load(), dirname(__DIR__) . '/templates');
$controller->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);

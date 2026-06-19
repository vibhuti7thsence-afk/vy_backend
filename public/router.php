<?php
// PHP built-in server router.
// Serve real static files directly; send everything else to index.php.
$path = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (is_file($path) && !str_ends_with($path, '.php')) {
    return false;
}
require __DIR__ . '/index.php';

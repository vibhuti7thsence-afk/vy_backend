<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

date_default_timezone_set('Asia/Kolkata');

\App\Core\Database::bootstrap();

// Ensure Railway Bucket allows public read so uploaded docs are viewable in the app.
$_s3 = \App\Services\S3StorageService::fromConfig();
if ($_s3 !== null) {
    try { $_s3->ensurePublicReadPolicy(); } catch (\Throwable $e) { /* non-fatal */ }
}
unset($_s3);

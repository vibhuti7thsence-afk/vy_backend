<?php

declare(strict_types=1);

return [
    'app' => [
        // Example for subfolder deployment: /yoga-api
        'base_path' => rtrim((string) (getenv('APP_BASE_PATH') ?: ''), '/'),
    ],
    'uploads' => [
        'donations_dir' => (string) (getenv('UPLOAD_DONATIONS_DIR') ?: (__DIR__ . '/../storage/uploads/donations')),
        'registrations_dir' => (string) (getenv('UPLOAD_REGISTRATIONS_DIR') ?: (__DIR__ . '/../storage/uploads/registrations')),
        'max_size_bytes' => (int) (getenv('UPLOAD_MAX_BYTES') ?: 5 * 1024 * 1024), // 5 MB
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
    ],
    'db' => [
        // Auto-detect pgsql from Railway env vars; explicit DB_DRIVER overrides.
        'driver' => strtolower((string) (getenv('DB_DRIVER') ?: ((getenv('DATABASE_URL') || getenv('PGHOST')) ? 'pgsql' : 'sqlite'))),
        'database' => (string) (getenv('DB_DATABASE') ?: (__DIR__ . '/../storage/app.db')),
        'host' => (string) (getenv('DB_HOST') ?: 'localhost'),
        'port' => (int) (getenv('DB_PORT') ?: 5432),
        'username' => (string) (getenv('DB_USERNAME') ?: ''),
        'password' => (string) (getenv('DB_PASSWORD') ?: ''),
        'charset' => (string) (getenv('DB_CHARSET') ?: 'utf8mb4'),
    ],
];

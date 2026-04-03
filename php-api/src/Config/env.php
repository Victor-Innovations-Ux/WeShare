<?php

// Load environment variables from .env file (if it exists)
$envFile = __DIR__ . '/../../.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Populate $_ENV from system environment variables (Docker, etc.)
$envKeys = [
    'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_PORT',
    'JWT_SECRET', 'JWT_EXPIRY',
    'GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GOOGLE_REDIRECT_URI',
    'APP_ENV', 'APP_URL', 'APP_DEBUG',
    'PHOTO_PATH', 'MAX_FILE_SIZE', 'ALLOWED_EXTENSIONS'
];

foreach ($envKeys as $key) {
    $sysValue = getenv($key);
    if ($sysValue !== false && !isset($_ENV[$key])) {
        $_ENV[$key] = $sysValue;
    }
}
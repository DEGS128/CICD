<?php
/**
 * API Configuration
 * Loads environment variables and sets up configuration
 */

// Load environment variables from .env file if it exists
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue; // Skip comments
        }
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
        putenv(trim($name) . '=' . trim($value));
    }
}

// Set default values if environment variables are not set
$config = [
    'database' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'name' => getenv('DB_NAME') ?: 'hr_integrated_db',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
    ],
    'jwt' => [
        'secret' => getenv('JWT_SECRET') ?: 'hospital-hr-super-secret-jwt-key-2024-change-in-production',
        'expiry' => 24 * 60 * 60, // 24 hours
    ],
    'email' => [
        'gmail_user' => getenv('GMAIL_USER') ?: '',
        'gmail_pass' => getenv('GMAIL_APP_PASSWORD') ?: '',
    ],
    'app' => [
        'url' => getenv('APP_URL') ?: 'http://localhost/hospital-HR4',
        'timezone' => 'UTC',
    ]
];

// Set timezone
date_default_timezone_set($config['app']['timezone']);

// Make config available globally
$GLOBALS['api_config'] = $config;


<?php
// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'klantportaal');
define('DB_USER', getenv('DB_USER') ?: 'dmg_user');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'dmg_password');
define('DB_CHARSET', 'utf8mb4');

// Application configuration
define('APP_NAME', 'DMG Klantportaal');
define('APP_URL', 'http://localhost:8080');
define('ENVIRONMENT', getenv('ENVIRONMENT') ?: 'development');

// Session configuration
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'dmg_portal_session');

// Security
define('HASH_ALGO', PASSWORD_BCRYPT);
define('HASH_COST', 10);

// Paths
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('MAX_UPLOAD_SIZE', 5242880); // 5MB

// Timezone
date_default_timezone_set('Europe/Amsterdam');

// Error reporting
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Start session
session_name(SESSION_NAME);
session_start();

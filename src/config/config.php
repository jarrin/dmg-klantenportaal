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

// Mail configuration (used when sending notification emails)
// Default to noreply address. Replace via .env in production.
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@dmg-portaal.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: APP_NAME);

// SMTP settings for PHPMailer (optional). If MAIL_USE_SMTP is true and valid SMTP_* values
// are provided, Ticket emails will be sent via SMTP using PHPMailer.
define('MAIL_USE_SMTP', getenv('MAIL_USE_SMTP') === '1' ? true : false);
define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
define('SMTP_PORT', getenv('SMTP_PORT') ?: '');
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
// SMTP_SECURE can be '' (none), 'ssl' or 'tls'
// Default to empty (no STARTTLS) so local SMTP catchers like MailHog (which don't support STARTTLS)
// won't cause PHPMailer to attempt STARTTLS by default during development.
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: '');

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

// API key for simple API authentication (set in .env)
define('API_KEY', getenv('API_KEY') ?: '');

// Start session
session_name(SESSION_NAME);
session_start();

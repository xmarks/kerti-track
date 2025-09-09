<?php

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'track');
define('DB_USER', 'root');
define('DB_PASS', '');

// API Endpoints
define('API_TRACKING_ENDPOINT', 'http://localhost:8111/api/v1/evoucher/tracking');
define('API_NEW_APPLICATIONS_ENDPOINT', 'http://localhost:8111/api/v1/evoucher/new_appforms');

// SMS API Configuration
define('SMS_API_URL', 'https://messaging.kristalcom.xyz/api/send');
define('SMS_API_USERNAME', 'identitek1');
define('SMS_API_PASSWORD', '88pNAS@wMxX7LCO');
define('SMS_SENDER', 'IdentiTek');

// Application Configuration
define('BASE_TRACKING_URL', 'https://track.identitek.al');
define('LOG_DIR', __DIR__ . '/logs');

// Data Retention (in days)
define('RETENTION_DAYS', 90);

// Rate Limiting
define('SMS_DELAY_MICROSECONDS', 100000); // 0.1 seconds between SMS sends

if (!file_exists(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

date_default_timezone_set('Europe/Tirane');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', LOG_DIR . '/php_errors.log');
?>

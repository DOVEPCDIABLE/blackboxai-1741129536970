<?php
// Application Configuration
define('APP_NAME', 'CryptoGift Trading Platform');
define('APP_URL', 'http://localhost:8000');
define('APP_ENV', 'development'); // 'development' or 'production'

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'cryptogift_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Email Configuration
define('SMTP_HOST', 'smtp.mailtrap.io');
define('SMTP_PORT', '2525');
define('SMTP_USER', 'your_smtp_username');
define('SMTP_PASS', 'your_smtp_password');
define('MAIL_FROM', 'noreply@cryptogift.com');
define('MAIL_FROM_NAME', 'CryptoGift Trading');

// Push Notification Configuration
define('FIREBASE_SERVER_KEY', 'your_firebase_server_key');
define('FIREBASE_SENDER_ID', 'your_firebase_sender_id');

// Referral Configuration
define('REFERRAL_BONUS_PERCENTAGE', 5); // 5% bonus on referred user's transaction

// Security Configuration
define('JWT_SECRET', 'your_jwt_secret_key');
define('PASSWORD_PEPPER', 'your_password_pepper');
define('SESSION_LIFETIME', 3600); // 1 hour

// File Upload Configuration
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png']);
define('UPLOAD_PATH', __DIR__ . '/../uploads');

// Default Theme
define('DEFAULT_THEME', 'light'); // 'light' or 'dark'

// Crypto Configuration
define('SUPPORTED_CRYPTOCURRENCIES', [
    'BTC' => 'Bitcoin',
    'ETH' => 'Ethereum',
    'TRX' => 'Tron',
    'LTC' => 'Litecoin',
    'DOGE' => 'Dogecoin'
]);

// Gift Card Configuration
define('GIFT_CARD_CATEGORIES', [
    'amazon' => 'Amazon Gift Cards',
    'apple' => 'Apple Gift Cards',
    'google' => 'Google Play Gift Cards',
    'steam' => 'Steam Gift Cards',
    'xbox' => 'Xbox Gift Cards',
    'psn' => 'PlayStation Network Cards',
    'itunes' => 'iTunes Gift Cards',
    'netflix' => 'Netflix Gift Cards'
]);

// Error Reporting
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

<?php
// Start session
session_start();

// Load configuration
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Load core classes
require_once __DIR__ . '/../src/Core/ErrorHandler.php';
require_once __DIR__ . '/../src/Core/Router.php';
require_once __DIR__ . '/../src/Core/BaseController.php';
require_once __DIR__ . '/../src/Core/NotificationService.php';

// Initialize error handler
ErrorHandler::getInstance();

require_once __DIR__ . '/../src/Core/routes.php';

// Initialize router
$router = new Router();

// Define routes
$router->get('/', ['HomeController', 'index']);
$router->get('/login', ['AuthController', 'loginForm']);
$router->post('/login', ['AuthController', 'login']);
$router->get('/register', ['AuthController', 'registerForm']);
$router->post('/register', ['AuthController', 'register']);
$router->get('/logout', ['AuthController', 'logout']);

// User routes
$router->get('/dashboard', ['UserController', 'dashboard']);
$router->get('/profile', ['UserController', 'profile']);
$router->post('/profile/update', ['UserController', 'updateProfile']);
$router->get('/notifications', ['NotificationController', 'index']);
$router->post('/notifications/mark-read', ['NotificationController', 'markAsRead']);

// Transaction routes
$router->get('/sell/crypto', ['TransactionController', 'cryptoForm']);
$router->post('/sell/crypto', ['TransactionController', 'processCrypto']);
$router->get('/sell/giftcard', ['TransactionController', 'giftcardForm']);
$router->post('/sell/giftcard', ['TransactionController', 'processGiftcard']);

// Wallet routes
$router->get('/wallet', ['WalletController', 'index']);
$router->post('/wallet/withdraw', ['WalletController', 'withdraw']);

// Admin routes
$router->get('/admin', ['AdminController', 'dashboard']);
$router->get('/admin/users', ['AdminController', 'users']);
$router->get('/admin/transactions', ['AdminController', 'transactions']);
$router->post('/admin/rates', ['AdminController', 'updateRates']);
$router->post('/admin/fees', ['AdminController', 'updateFees']);

// 2FA routes
$router->get('/2fa/setup', ['TwoFactorAuthController', 'setup']);
$router->post('/2fa/verify', ['TwoFactorAuthController', 'verify']);
$router->post('/2fa/disable', ['TwoFactorAuthController', 'disable']);

// Settings routes
$router->post('/settings/theme', ['SettingsController', 'updateTheme']);
$router->post('/settings/notifications', ['SettingsController', 'updateNotificationPreferences']);

// API routes for push notifications
$router->post('/api/push-token', ['NotificationController', 'storePushToken']);
$router->delete('/api/push-token', ['NotificationController', 'removePushToken']);

// 404 handler
$router->setNotFound(function() {
    header("HTTP/1.0 404 Not Found");
    echo '404 Page Not Found';
});

// Get current URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Dispatch router
echo $router->dispatch($uri);

<?php
// Include necessary controllers
require_once __DIR__ . '/../Controllers/AuthController.php';
require_once __DIR__ . '/../Controllers/UserController.php';
require_once __DIR__ . '/../Controllers/TransactionController.php';

// Initialize controllers
$authController = new AuthController();
$userController = new UserController();
$transactionController = new TransactionController();

// Define routes
$router->get('/login', [$authController, 'loginForm']);
$router->post('/login', [$authController, 'login']);
$router->get('/register', [$authController, 'registerForm']);
$router->post('/register', [$authController, 'register']);
$router->get('/logout', [$authController, 'logout']);
$router->get('/forgot-password', [$authController, 'forgotPasswordForm']);
$router->post('/forgot-password', [$authController, 'forgotPassword']);
$router->get('/reset-password', [$authController, 'resetPasswordForm']);
$router->post('/reset-password', [$authController, 'resetPassword']);
$router->get('/2fa/setup', [$authController, 'setup2FA']);
$router->post('/2fa/verify', [$authController, 'verify2FA']);
$router->post('/2fa/disable', [$authController, 'disable2FA']);

$router->get('/dashboard', [$userController, 'dashboard']);
$router->get('/profile', [$userController, 'profile']);
$router->post('/profile/update', [$userController, 'updateProfile']);
$router->get('/settings', [$userController, 'settings']);
$router->post('/settings/update', [$userController, 'updateSettings']);
$router->post('/settings/change-password', [$userController, 'changePassword']);
$router->post('/settings/add-bank-account', [$userController, 'addBankAccount']);
$router->get('/referrals', [$userController, 'referrals']);
$router->get('/notifications', [$userController, 'notifications']);
$router->post('/notifications/mark-read', [$userController, 'markNotificationRead']);
$router->get('/transactions', [$userController, 'transactionHistory']);

$router->get('/sell/crypto', [$transactionController, 'cryptoForm']);
$router->post('/sell/crypto', [$transactionController, 'processCrypto']);
$router->get('/sell/giftcard', [$transactionController, 'giftCardForm']);
$router->post('/sell/giftcard', [$transactionController, 'processGiftCard']);
$router->get('/transaction/crypto/{id}', [$transactionController, 'viewTransaction']);
$router->get('/transaction/giftcard/{id}', [$transactionController, 'viewTransaction']);
$router->post('/transaction/cancel/{id}', [$transactionController, 'cancelTransaction']);
$router->get('/api/subcategories', [$transactionController, 'getSubcategories']);

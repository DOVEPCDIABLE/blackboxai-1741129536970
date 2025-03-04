<?php
class AuthController extends BaseController {
    private $userModel;
    private $notificationService;

    public function __construct() {
        $this->userModel = new User();
        $this->notificationService = NotificationService::getInstance();
    }

    public function loginForm() {
        if ($this->isAuthenticated()) {
            $this->redirect('/dashboard');
        }
        return $this->render('auth/login', [
            'pageTitle' => 'Login'
        ]);
    }

    public function login() {
        try {
            $this->validateCSRFToken();

            $email = $this->sanitizeInput($_POST['email']);
            $password = $_POST['password'];

            if (empty($email) || empty($password)) {
                throw new Exception('Email and password are required');
            }

            $user = $this->userModel->findByEmail($email);
            if (!$user || !$this->userModel->verifyPassword($password, $user['password'])) {
                throw new Exception('Invalid email or password');
            }

            if (!$user['is_active']) {
                throw new Exception('Your account has been deactivated. Please contact support.');
            }

            // Check if 2FA is enabled
            if ($user['twofactor_enabled']) {
                $_SESSION['2fa_user_id'] = $user['id'];
                $this->redirect('/2fa/verify');
            }

            $this->createUserSession($user);
            
            // Record login activity
            $this->logLoginActivity($user['id']);

            $this->redirect('/dashboard');
        } catch (Exception $e) {
            $this->setFlashMessage('error', $e->getMessage());
            $this->redirect('/login');
        }
    }

    public function registerForm() {
        if ($this->isAuthenticated()) {
            $this->redirect('/dashboard');
        }
        return $this->render('auth/register', [
            'pageTitle' => 'Register',
            'referralCode' => $_GET['ref'] ?? null
        ]);
    }

    public function register() {
        try {
            $this->validateCSRFToken();

            $data = [
                'name' => $this->sanitizeInput($_POST['name']),
                'email' => $this->sanitizeInput($_POST['email']),
                'password' => $_POST['password'],
                'password_confirm' => $_POST['password_confirm'],
                'referred_by' => $this->sanitizeInput($_POST['referral_code'] ?? null)
            ];

            // Validate input
            $this->validateRegistrationData($data);

            // Check if email exists
            if ($this->userModel->findByEmail($data['email'])) {
                throw new Exception('Email already registered');
            }

            // Validate referral code if provided
            if ($data['referred_by'] && !$this->userModel->validateReferralCode($data['referred_by'])) {
                throw new Exception('Invalid referral code');
            }

            // Create user
            $userId = $this->userModel->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'referred_by' => $data['referred_by']
            ]);

            // Send welcome email
            $this->notificationService->sendEmail(
                $data['email'],
                'Welcome to ' . APP_NAME,
                'welcome',
                ['name' => $data['name']]
            );

            $this->setFlashMessage('success', 'Registration successful! Please login.');
            $this->redirect('/login');
        } catch (Exception $e) {
            $this->setFlashMessage('error', $e->getMessage());
            $this->redirect('/register');
        }
    }

    public function logout() {
        // Record logout activity
        if (isset($_SESSION['user_id'])) {
            $this->logLogoutActivity($_SESSION['user_id']);
        }

        // Clear all session data
        session_destroy();
        
        // Start a new session for flash messages
        session_start();
        $this->setFlashMessage('success', 'You have been logged out successfully');
        
        $this->redirect('/login');
    }

    public function forgotPasswordForm() {
        return $this->render('auth/forgot-password', [
            'pageTitle' => 'Forgot Password'
        ]);
    }

    public function forgotPassword() {
        try {
            $this->validateCSRFToken();

            $email = $this->sanitizeInput($_POST['email']);
            $user = $this->userModel->findByEmail($email);

            if (!$user) {
                throw new Exception('If the email exists, password reset instructions will be sent.');
            }

            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Save reset token
            $this->userModel->saveResetToken($user['id'], $token, $expires);

            // Send reset email
            $this->notificationService->sendEmail(
                $email,
                'Password Reset Request',
                'password-reset',
                [
                    'name' => $user['name'],
                    'reset_link' => APP_URL . "/reset-password?token=" . $token
                ]
            );

            $this->setFlashMessage('success', 'If the email exists, password reset instructions will be sent.');
            $this->redirect('/login');
        } catch (Exception $e) {
            $this->setFlashMessage('error', $e->getMessage());
            $this->redirect('/forgot-password');
        }
    }

    public function resetPasswordForm() {
        $token = $this->sanitizeInput($_GET['token']);
        
        if (!$token || !$this->userModel->isValidResetToken($token)) {
            $this->setFlashMessage('error', 'Invalid or expired reset token');
            $this->redirect('/login');
        }

        return $this->render('auth/reset-password', [
            'pageTitle' => 'Reset Password',
            'token' => $token
        ]);
    }

    public function resetPassword() {
        try {
            $this->validateCSRFToken();

            $token = $this->sanitizeInput($_POST['token']);
            $password = $_POST['password'];
            $passwordConfirm = $_POST['password_confirm'];

            if ($password !== $passwordConfirm) {
                throw new Exception('Passwords do not match');
            }

            $userId = $this->userModel->getUserIdByResetToken($token);
            if (!$userId) {
                throw new Exception('Invalid or expired reset token');
            }

            // Update password
            $this->userModel->update($userId, ['password' => $password]);

            // Invalidate reset token
            $this->userModel->invalidateResetToken($token);

            $this->setFlashMessage('success', 'Password has been reset successfully. Please login with your new password.');
            $this->redirect('/login');
        } catch (Exception $e) {
            $this->setFlashMessage('error', $e->getMessage());
            $this->redirect('/reset-password?token=' . $token);
        }
    }

    public function setup2FA() {
        if (!$this->isAuthenticated()) {
            $this->redirect('/login');
        }

        try {
            require_once __DIR__ . '/../../vendor/autoload.php';
            $google2fa = new \PragmaRX\Google2FA\Google2FA();

            $secret = $google2fa->generateSecretKey();
            $qrCodeUrl = $google2fa->getQRCodeUrl(
                APP_NAME,
                $this->getCurrentUser()['email'],
                $secret
            );

            return $this->render('auth/2fa-setup', [
                'pageTitle' => 'Setup Two-Factor Authentication',
                'secret' => $secret,
                'qrCodeUrl' => $qrCodeUrl
            ]);
        } catch (Exception $e) {
            $this->setFlashMessage('error', 'Failed to setup 2FA');
            $this->redirect('/settings');
        }
    }

    public function verify2FA() {
        try {
            $this->validateCSRFToken();

            $code = $this->sanitizeInput($_POST['code']);
            $userId = $_SESSION['2fa_user_id'] ?? null;

            if (!$userId) {
                throw new Exception('Invalid session');
            }

            $user = $this->userModel->findById($userId);
            if (!$user) {
                throw new Exception('User not found');
            }

            require_once __DIR__ . '/../../vendor/autoload.php';
            $google2fa = new \PragmaRX\Google2FA\Google2FA();

            if (!$google2fa->verifyKey($user['twofactor_secret'], $code)) {
                throw new Exception('Invalid verification code');
            }

            // Clear 2FA session
            unset($_SESSION['2fa_user_id']);

            // Create user session
            $this->createUserSession($user);

            $this->redirect('/dashboard');
        } catch (Exception $e) {
            $this->setFlashMessage('error', $e->getMessage());
            $this->redirect('/2fa/verify');
        }
    }

    public function disable2FA() {
        if (!$this->isAuthenticated()) {
            $this->redirect('/login');
        }

        try {
            $this->validateCSRFToken();

            $password = $_POST['password'];
            $user = $this->getCurrentUser();

            if (!$this->userModel->verifyPassword($password, $user['password'])) {
                throw new Exception('Invalid password');
            }

            $this->userModel->disable2FA($user['id']);

            $this->setFlashMessage('success', 'Two-factor authentication has been disabled');
            $this->redirect('/settings');
        } catch (Exception $e) {
            $this->setFlashMessage('error', $e->getMessage());
            $this->redirect('/settings');
        }
    }

    private function validateRegistrationData($data) {
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            throw new Exception('All fields are required');
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        if (strlen($data['password']) < 8) {
            throw new Exception('Password must be at least 8 characters long');
        }

        if ($data['password'] !== $data['password_confirm']) {
            throw new Exception('Passwords do not match');
        }
    }

    private function createUserSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['is_admin'] = $user['is_admin'] ?? false;
        $_SESSION['theme'] = $user['theme_preference'] ?? DEFAULT_THEME;
    }

    private function logLoginActivity($userId) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_activity_logs (
                    user_id, activity_type, ip_address, user_agent, created_at
                ) VALUES (
                    :user_id, 'login', :ip_address, :user_agent, NOW()
                )
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':ip_address' => $_SERVER['REMOTE_ADDR'],
                ':user_agent' => $_SERVER['HTTP_USER_AGENT']
            ]);
        } catch (PDOException $e) {
            error_log("Error logging login activity: " . $e->getMessage());
        }
    }

    private function logLogoutActivity($userId) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_activity_logs (
                    user_id, activity_type, ip_address, user_agent, created_at
                ) VALUES (
                    :user_id, 'logout', :ip_address, :user_agent, NOW()
                )
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':ip_address' => $_SERVER['REMOTE_ADDR'],
                ':user_agent' => $_SERVER['HTTP_USER_AGENT']
            ]);
        } catch (PDOException $e) {
            error_log("Error logging logout activity: " . $e->getMessage());
        }
    }
}

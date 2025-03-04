<?php
class UserController extends BaseController {
    private $userModel;
    private $walletModel;
    private $transactionModel;
    private $notificationService;

    public function __construct() {
        $this->requireAuth();
        
        $this->userModel = new User();
        $this->walletModel = new Wallet();
        $this->transactionModel = new Transaction();
        $this->notificationService = NotificationService::getInstance();
    }

    public function dashboard() {
        $user = $this->getCurrentUser();
        $walletBalance = $this->walletModel->getBalance($user['id']);
        
        // Get recent transactions
        $recentTransactions = $this->transactionModel->getUserTransactions(
            $user['id'],
            5  // Limit to 5 recent transactions
        );

        // Get referral statistics
        $referrals = $this->userModel->getReferrals($user['id']);
        $referralStats = [
            'total_referrals' => count($referrals),
            'active_referrals' => count(array_filter($referrals, function($ref) {
                return $ref['total_transactions'] > 0;
            })),
            'total_bonus' => array_sum(array_column($referrals, 'total_bonus'))
        ];

        // Get transaction statistics
        $transactionStats = $this->transactionModel->getTransactionStats($user['id']);

        return $this->render('dashboard/user_dashboard', [
            'pageTitle' => 'Dashboard',
            'user' => $user,
            'walletBalance' => $walletBalance,
            'recentTransactions' => $recentTransactions,
            'referralStats' => $referralStats,
            'transactionStats' => $transactionStats
        ]);
    }

    public function profile() {
        $user = $this->getCurrentUser();
        $bankAccounts = $this->userModel->getBankAccounts($user['id']);

        return $this->render('user/profile', [
            'pageTitle' => 'My Profile',
            'user' => $user,
            'bankAccounts' => $bankAccounts
        ]);
    }

    public function updateProfile() {
        try {
            $this->validateCSRFToken();

            $userId = $this->getCurrentUser()['id'];
            $data = [
                'name' => $this->sanitizeInput($_POST['name']),
                'phone' => $this->sanitizeInput($_POST['phone'] ?? null),
                'country' => $this->sanitizeInput($_POST['country'] ?? null)
            ];

            // Handle profile picture upload
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $uploadedFile = $_FILES['profile_picture'];
                $errors = $this->validateFileUpload($uploadedFile, ['image/jpeg', 'image/png'], 2 * 1024 * 1024);
                
                if (empty($errors)) {
                    $filename = 'profile_' . $userId . '_' . time() . '.' . 
                               pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
                    $uploadPath = UPLOAD_PATH . '/profiles/' . $filename;
                    
                    if (move_uploaded_file($uploadedFile['tmp_name'], $uploadPath)) {
                        $data['profile_picture'] = $filename;
                    }
                }
            }

            if ($this->userModel->update($userId, $data)) {
                $this->setFlashMessage('success', 'Profile updated successfully');
            } else {
                throw new Exception('Failed to update profile');
            }
        } catch (Exception $e) {
            $this->setFlashMessage('error', $e->getMessage());
        }

        $this->redirect('/profile');
    }

    public function settings() {
        $user = $this->getCurrentUser();

        return $this->render('user/settings', [
            'pageTitle' => 'Account Settings',
            'user' => $user,
            'notificationPreferences' => json_decode($user['notification_preferences'], true)
        ]);
    }

    public function updateSettings() {
        try {
            $this->validateCSRFToken();

            $userId = $this->getCurrentUser()['id'];
            $settings = [
                'theme_preference' => $this->sanitizeInput($_POST['theme']),
                'notification_preferences' => [
                    'email' => isset($_POST['email_notifications']),
                    'push' => isset($_POST['push_notifications']),
                    'in_app' => isset($_POST['in_app_notifications'])
                ]
            ];

            // Update theme preference
            $this->userModel->updateTheme($userId, $settings['theme_preference']);

            // Update notification preferences
            $this->userModel->updateNotificationPreferences($userId, $settings['notification_preferences']);

            // Update session theme
            $_SESSION['theme'] = $settings['theme_preference'];

            $this->setFlashMessage('success', 'Settings updated successfully');
        } catch (Exception $e) {
            $this->setFlashMessage('error', $e->getMessage());
        }

        $this->redirect('/settings');
    }

    public function changePassword() {
        try {
            $this->validateCSRFToken();

            $userId = $this->getCurrentUser()['id'];
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];

            // Verify current password
            if (!$this->userModel->verifyPassword($currentPassword, $this->getCurrentUser()['password'])) {
                throw new Exception('Current password is incorrect');
            }

            // Validate new password
            if (strlen($newPassword) < 8) {
                throw new Exception('New password must be at least 8 characters long');
            }

            if ($newPassword !== $confirmPassword) {
                throw new Exception('New passwords do not match');
            }

            // Update password
            if ($this->userModel->update($userId, ['password' => $newPassword])) {
                // Send password change notification
                $this->notificationService->sendEmail(
                    $this->getCurrentUser()['email'],
                    'Password Changed',
                    'password-changed',
                    ['name' => $this->getCurrentUser()['name']]
                );

                $this->setFlashMessage('success', 'Password changed successfully');
            } else {
                throw new Exception('Failed to change password');
            }
        } catch (Exception $e) {
            $this->setFlashMessage('error', $e->getMessage());
        }

        $this->redirect('/settings');
    }

    public function addBankAccount() {
        try {
            $this->validateCSRFToken();

            $userId = $this->getCurrentUser()['id'];
            $data = [
                'bank_name' => $this->sanitizeInput($_POST['bank_name']),
                'account_number' => $this->sanitizeInput($_POST['account_number']),
                'account_name' => $this->sanitizeInput($_POST['account_name'])
            ];

            // Validate input
            if (empty($data['bank_name']) || empty($data['account_number']) || empty($data['account_name'])) {
                throw new Exception('All fields are required');
            }

            if ($this->userModel->addBankAccount($userId, $data)) {
                $this->setFlashMessage('success', 'Bank account added successfully');
            } else {
                throw new Exception('Failed to add bank account');
            }
        } catch (Exception $e) {
            $this->setFlashMessage('error', $e->getMessage());
        }

        $this->redirect('/profile');
    }

    public function referrals() {
        $user = $this->getCurrentUser();
        $referrals = $this->userModel->getReferrals($user['id']);

        return $this->render('user/referrals', [
            'pageTitle' => 'My Referrals',
            'user' => $user,
            'referrals' => $referrals,
            'referralLink' => APP_URL . '/register?ref=' . $user['referral_code']
        ]);
    }

    public function notifications() {
        $user = $this->getCurrentUser();
        $notifications = $this->notificationService->getUnreadNotifications($user['id']);

        return $this->render('user/notifications', [
            'pageTitle' => 'Notifications',
            'notifications' => $notifications
        ]);
    }

    public function markNotificationRead() {
        try {
            $this->validateCSRFToken();

            $notificationId = (int)$_POST['notification_id'];
            $userId = $this->getCurrentUser()['id'];

            if ($this->notificationService->markNotificationAsRead($notificationId, $userId)) {
                return $this->json(['success' => true]);
            } else {
                throw new Exception('Failed to mark notification as read');
            }
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    public function transactionHistory() {
        $user = $this->getCurrentUser();
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $transactions = $this->transactionModel->getUserTransactions($user['id'], $limit, $offset);
        $totalTransactions = $this->transactionModel->getUserTransactionCount($user['id']);
        $totalPages = ceil($totalTransactions / $limit);

        return $this->render('user/transaction_history', [
            'pageTitle' => 'Transaction History',
            'transactions' => $transactions,
            'currentPage' => $page,
            'totalPages' => $totalPages
        ]);
    }

    public function storePushToken() {
        try {
            $this->validateCSRFToken();

            $userId = $this->getCurrentUser()['id'];
            $token = $this->sanitizeInput($_POST['token']);

            if (empty($token)) {
                throw new Exception('Push token is required');
            }

            if ($this->notificationService->storePushToken($userId, $token)) {
                return $this->json(['success' => true]);
            } else {
                throw new Exception('Failed to store push token');
            }
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}

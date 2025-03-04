<?php
class BaseController {
    protected function render($view, $data = []) {
        // Extract data to make it available in view
        extract($data);
        
        // Start output buffering
        ob_start();
        
        // Include header
        include __DIR__ . '/../../views/layouts/header.php';
        
        // Include the view file
        include __DIR__ . '/../../views/' . $view . '.php';
        
        // Include footer
        include __DIR__ . '/../../views/layouts/footer.php';
        
        // Get contents and clean buffer
        $content = ob_get_clean();
        
        return $content;
    }

    protected function json($data, $statusCode = 200) {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    protected function redirect($path) {
        header('Location: ' . APP_URL . $path);
        exit;
    }

    protected function isAuthenticated() {
        return isset($_SESSION['user_id']);
    }

    protected function requireAuth() {
        if (!$this->isAuthenticated()) {
            $this->redirect('/login');
        }
    }

    protected function requireAdmin() {
        if (!$this->isAuthenticated() || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
            $this->redirect('/login');
        }
    }

    protected function getCurrentUser() {
        if ($this->isAuthenticated()) {
            require_once __DIR__ . '/../Models/User.php';
            $userModel = new User();
            return $userModel->findById($_SESSION['user_id']);
        }
        return null;
    }

    protected function getThemePreference() {
        return isset($_SESSION['theme']) ? $_SESSION['theme'] : DEFAULT_THEME;
    }

    protected function validateCSRFToken() {
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
            $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $this->json(['error' => 'Invalid CSRF token'], 403);
        }
    }

    protected function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function setFlashMessage($type, $message) {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message
        ];
    }

    protected function getFlashMessage() {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }

    protected function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    protected function validateFileUpload($file, $allowedTypes = ALLOWED_FILE_TYPES, $maxSize = MAX_FILE_SIZE) {
        $errors = [];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed';
            return $errors;
        }

        // Check file size
        if ($file['size'] > $maxSize) {
            $errors[] = 'File size exceeds limit';
        }

        // Check file type
        $fileInfo = pathinfo($file['name']);
        $extension = strtolower($fileInfo['extension']);
        if (!in_array($extension, $allowedTypes)) {
            $errors[] = 'Invalid file type';
        }

        return $errors;
    }

    protected function logError($message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message " . json_encode($context) . PHP_EOL;
        error_log($logMessage, 3, __DIR__ . '/../../logs/error.log');
    }
}

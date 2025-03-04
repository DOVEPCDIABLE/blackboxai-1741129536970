<?php
class ErrorHandler {
    private static $instance = null;
    private $logFile;
    private $displayErrors;

    private function __construct() {
        $this->logFile = __DIR__ . '/../../logs/error.log';
        $this->displayErrors = APP_ENV === 'development';
        
        // Set error and exception handlers
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleFatalError']);
        
        // Ensure log directory exists and is writable
        $this->initializeLogDirectory();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializeLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        if (!is_writable($logDir)) {
            throw new Exception("Log directory is not writable: $logDir");
        }
    }

    public function handleError($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting
            return false;
        }

        $errorType = $this->getErrorType($errno);
        $message = "$errorType: $errstr in $errfile on line $errline";
        
        $this->logError($message);

        if ($this->displayErrors) {
            echo $this->formatErrorForDisplay($message);
        } else {
            $this->showGenericError();
        }

        // Don't execute PHP internal error handler
        return true;
    }

    public function handleException($exception) {
        $message = "Exception: " . $exception->getMessage() . 
                  " in " . $exception->getFile() . 
                  " on line " . $exception->getLine() . 
                  "\nStack trace: " . $exception->getTraceAsString();
        
        $this->logError($message);

        if ($this->displayErrors) {
            echo $this->formatErrorForDisplay($message);
        } else {
            $this->showGenericError();
        }
    }

    public function handleFatalError() {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $message = "FATAL ERROR: {$error['message']} in {$error['file']} on line {$error['line']}";
            
            $this->logError($message);

            if ($this->displayErrors) {
                echo $this->formatErrorForDisplay($message);
            } else {
                $this->showGenericError();
            }
        }
    }

    private function logError($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        
        // Add request information
        $logMessage .= "Request URI: " . $_SERVER['REQUEST_URI'] . PHP_EOL;
        $logMessage .= "Request Method: " . $_SERVER['REQUEST_METHOD'] . PHP_EOL;
        if (!empty($_POST)) {
            $logMessage .= "POST Data: " . print_r($_POST, true) . PHP_EOL;
        }
        $logMessage .= "User IP: " . $_SERVER['REMOTE_ADDR'] . PHP_EOL;
        $logMessage .= str_repeat('-', 80) . PHP_EOL;

        error_log($logMessage, 3, $this->logFile);
    }

    private function formatErrorForDisplay($message) {
        return "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; margin: 10px; border: 1px solid #f5c6cb; border-radius: 4px;'>
                <h3 style='margin-top: 0;'>Application Error</h3>
                <pre style='margin: 0; white-space: pre-wrap;'>" . htmlspecialchars($message) . "</pre>
                </div>";
    }

    private function showGenericError() {
        http_response_code(500);
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; margin: 10px; border: 1px solid #f5c6cb; border-radius: 4px;'>
              <h3 style='margin-top: 0;'>System Error</h3>
              <p style='margin: 0;'>An unexpected error occurred. Please try again later.</p>
              </div>";
    }

    private function getErrorType($errno) {
        switch ($errno) {
            case E_ERROR:
                return 'ERROR';
            case E_WARNING:
                return 'WARNING';
            case E_PARSE:
                return 'PARSE ERROR';
            case E_NOTICE:
                return 'NOTICE';
            case E_CORE_ERROR:
                return 'CORE ERROR';
            case E_CORE_WARNING:
                return 'CORE WARNING';
            case E_COMPILE_ERROR:
                return 'COMPILE ERROR';
            case E_COMPILE_WARNING:
                return 'COMPILE WARNING';
            case E_USER_ERROR:
                return 'USER ERROR';
            case E_USER_WARNING:
                return 'USER WARNING';
            case E_USER_NOTICE:
                return 'USER NOTICE';
            case E_STRICT:
                return 'STRICT NOTICE';
            case E_RECOVERABLE_ERROR:
                return 'RECOVERABLE ERROR';
            case E_DEPRECATED:
                return 'DEPRECATED';
            case E_USER_DEPRECATED:
                return 'USER DEPRECATED';
            default:
                return 'UNKNOWN ERROR';
        }
    }

    // Prevent cloning of the instance
    private function __clone() {}

    // Prevent unserializing of the instance
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

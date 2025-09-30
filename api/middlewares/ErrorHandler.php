<?php
/**
 * Error Handler Middleware
 * Provides centralized error handling and logging
 */

require_once __DIR__ . '/../utils/Response.php';

class ErrorHandler {
    /**
     * Handle exceptions and errors
     */
    public function handle($exception) {
        $this->logError($exception);
        
        if ($exception instanceof ValidationException) {
            Response::validationError($exception->getErrors(), $exception->getMessage());
        } elseif ($exception instanceof AuthenticationException) {
            Response::unauthorized($exception->getMessage());
        } elseif ($exception instanceof AuthorizationException) {
            Response::forbidden($exception->getMessage());
        } elseif ($exception instanceof NotFoundException) {
            Response::notFound($exception->getMessage());
        } elseif ($exception instanceof PDOException) {
            Response::error('Database error occurred', 500);
        } else {
            Response::error('An unexpected error occurred', 500);
        }
    }

    /**
     * Log error details
     */
    private function logError($exception) {
        $errorMessage = sprintf(
            "[%s] %s in %s on line %d: %s",
            date('Y-m-d H:i:s'),
            get_class($exception),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getMessage()
        );
        
        error_log($errorMessage);
        
        // Log stack trace for debugging
        error_log("Stack trace: " . $exception->getTraceAsString());
    }

    /**
     * Handle fatal errors
     */
    public function handleFatalError() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->logError(new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
            Response::error('A fatal error occurred', 500);
        }
    }
}

/**
 * Custom Exception Classes
 */
class ValidationException extends Exception {
    private $errors;

    public function __construct($message, $errors = []) {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getErrors() {
        return $this->errors;
    }
}

class AuthenticationException extends Exception {
    public function __construct($message = 'Authentication failed') {
        parent::__construct($message);
    }
}

class AuthorizationException extends Exception {
    public function __construct($message = 'Access denied') {
        parent::__construct($message);
    }
}

class NotFoundException extends Exception {
    public function __construct($message = 'Resource not found') {
        parent::__construct($message);
    }
}

// Register error handlers
register_shutdown_function([new ErrorHandler(), 'handleFatalError']);
set_exception_handler([new ErrorHandler(), 'handle']);


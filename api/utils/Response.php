<?php
/**
 * Response Utility Class
 * Provides standardized JSON responses for the API
 */

class Response {
    /**
     * Send a successful response
     */
    public static function success($data = null, $message = 'Success', $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ]);
        exit;
    }

    /**
     * Send an error response
     */
    public static function error($message = 'An error occurred', $statusCode = 500, $details = null) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'details' => $details,
            'timestamp' => date('c')
        ]);
        exit;
    }

    /**
     * Send a validation error response
     */
    public static function validationError($errors, $message = 'Validation failed') {
        self::error($message, 400, $errors);
    }

    /**
     * Send a not found response
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404);
    }

    /**
     * Send an unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized access') {
        self::error($message, 401);
    }

    /**
     * Send a forbidden response
     */
    public static function forbidden($message = 'Access forbidden') {
        self::error($message, 403);
    }

    /**
     * Send a method not allowed response
     */
    public static function methodNotAllowed($message = 'Method not allowed') {
        self::error($message, 405);
    }

    /**
     * Send a created response
     */
    public static function created($data = null, $message = 'Resource created successfully') {
        self::success($data, $message, 201);
    }

    /**
     * Send a no content response
     */
    public static function noContent($message = 'No content') {
        http_response_code(204);
        exit;
    }

    /**
     * Send a paginated response
     */
    public static function paginated($data, $pagination, $message = 'Success') {
        self::success([
            'items' => $data,
            'pagination' => $pagination
        ], $message);
    }
}


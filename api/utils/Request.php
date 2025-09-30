<?php
/**
 * Request Utility Class
 * Handles HTTP request parsing and validation
 */

class Request {
    private $data = [];
    private $files = [];

    public function __construct() {
        $this->parseRequest();
    }

    /**
     * Parse the incoming request
     */
    private function parseRequest() {
        // Parse JSON body
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $this->data = json_decode($input, true) ?? [];
        }

        // Parse form data
        if (!empty($_POST)) {
            $this->data = array_merge($this->data, $_POST);
        }

        // Parse query parameters
        if (!empty($_GET)) {
            $this->data = array_merge($this->data, $_GET);
        }

        // Parse files
        $this->files = $_FILES ?? [];
    }

    /**
     * Get request data
     */
    public function getData($key = null, $default = null) {
        if ($key === null) {
            return $this->data;
        }
        return $this->data[$key] ?? $default;
    }

    /**
     * Get request files
     */
    public function getFiles($key = null) {
        if ($key === null) {
            return $this->files;
        }
        return $this->files[$key] ?? null;
    }

    /**
     * Get the request method
     */
    public function getMethod() {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Get the request path
     */
    public function getPath() {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * Get request headers
     */
    public function getHeaders() {
        return getallheaders() ?: [];
    }

    /**
     * Get a specific header
     */
    public function getHeader($name) {
        $headers = $this->getHeaders();
        return $headers[$name] ?? null;
    }

    /**
     * Get authorization header
     */
    public function getAuthHeader() {
        $auth = $this->getHeader('Authorization');
        if ($auth && strpos($auth, 'Bearer ') === 0) {
            return substr($auth, 7);
        }
        return null;
    }

    /**
     * Validate required fields
     */
    public function validateRequired($fields) {
        $errors = [];
        foreach ($fields as $field) {
            if (!isset($this->data[$field]) || empty($this->data[$field])) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        return $errors;
    }

    /**
     * Sanitize string input
     */
    public function sanitizeString($value) {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate email
     */
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate integer
     */
    public function validateInteger($value) {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Get pagination parameters
     */
    public function getPagination() {
        $page = max(1, (int)($this->getData('page', 1)));
        $limit = min(100, max(1, (int)($this->getData('limit', 20))));
        $offset = ($page - 1) * $limit;

        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
}


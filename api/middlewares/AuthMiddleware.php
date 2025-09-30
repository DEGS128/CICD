<?php
/**
 * Authentication Middleware
 * Handles JWT token validation and user authentication
 */

require_once __DIR__ . '/../utils/Response.php';

class AuthMiddleware {
    private $pdo;
    private $secretKey;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->secretKey = getenv('JWT_SECRET') ?: 'your-secret-key-change-in-production';
    }

    /**
     * Authenticate the request
     */
    public function authenticate() {
        $token = $this->getTokenFromRequest();
        
        if (!$token) {
            return false;
        }

        try {
            $payload = $this->validateToken($token);
            if (!$payload) {
                return false;
            }

            // Verify user still exists and is active
            if (!$this->verifyUser($payload['user_id'])) {
                return false;
            }

            // Store user info in global scope for controllers
            $GLOBALS['current_user'] = $payload;
            return true;

        } catch (Exception $e) {
            error_log("Auth middleware error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get token from request headers
     */
    private function getTokenFromRequest() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Validate JWT token
     */
    private function validateToken($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        list($header, $payload, $signature) = $parts;

        // Verify signature
        $expectedSignature = base64url_encode(
            hash_hmac('sha256', $header . '.' . $payload, $this->secretKey, true)
        );

        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }

        // Decode payload
        $payloadData = json_decode(base64url_decode($payload), true);
        
        if (!$payloadData) {
            return false;
        }

        // Check expiration
        if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
            return false;
        }

        return $payloadData;
    }

    /**
     * Verify user exists and is active
     */
    private function verifyUser($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.UserID, u.EmployeeID, u.Username, u.IsActive, u.RoleID, r.RoleName
                FROM Users u
                JOIN Roles r ON u.RoleID = r.RoleID
                WHERE u.UserID = :user_id AND u.IsActive = 1
            ");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (PDOException $e) {
            error_log("User verification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate JWT token
     */
    public function generateToken($userId, $employeeId, $username, $roleId, $roleName) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $userId,
            'employee_id' => $employeeId,
            'username' => $username,
            'role_id' => $roleId,
            'role_name' => $roleName,
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60) // 24 hours
        ]);

        $headerEncoded = base64url_encode($header);
        $payloadEncoded = base64url_encode($payload);
        $signature = base64url_encode(
            hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $this->secretKey, true)
        );

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signature;
    }

    /**
     * Get current user info
     */
    public function getCurrentUser() {
        return $GLOBALS['current_user'] ?? null;
    }

    /**
     * Check if user has required role
     */
    public function hasRole($requiredRole) {
        $user = $this->getCurrentUser();
        return $user && $user['role_name'] === $requiredRole;
    }

    /**
     * Check if user has any of the required roles
     */
    public function hasAnyRole($requiredRoles) {
        $user = $this->getCurrentUser();
        return $user && in_array($user['role_name'], $requiredRoles);
    }
}

/**
 * Base64 URL encode
 */
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64 URL decode
 */
function base64url_decode($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}


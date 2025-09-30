<?php
/**
 * User Model
 * Handles user-related database operations
 */

class User {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * Get user by username
     */
    public function getUserByUsername($username) {
        $sql = "SELECT
                    u.UserID, u.EmployeeID, u.Username, u.PasswordHash, u.RoleID, u.IsActive,
                    u.IsTwoFactorEnabled, u.TwoFactorEmailCode, u.TwoFactorCodeExpiry,
                    r.RoleName,
                    e.FirstName, e.LastName, e.Email
                FROM Users u
                JOIN Roles r ON u.RoleID = r.RoleID
                JOIN Employees e ON u.EmployeeID = e.EmployeeID
                WHERE u.Username = :username";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get user by email
     */
    public function getUserByEmail($email) {
        $sql = "SELECT
                    u.UserID, u.EmployeeID, u.Username, u.RoleID, u.IsActive,
                    r.RoleName,
                    e.FirstName, e.LastName, e.Email
                FROM Users u
                JOIN Roles r ON u.RoleID = r.RoleID
                JOIN Employees e ON u.EmployeeID = e.EmployeeID
                WHERE e.Email = :email";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get user by ID
     */
    public function getUserById($userId) {
        $sql = "SELECT
                    u.UserID, u.EmployeeID, u.Username, u.RoleID, u.IsActive,
                    u.IsTwoFactorEnabled,
                    r.RoleName,
                    e.FirstName, e.LastName, e.Email, e.JobTitle, e.DepartmentID,
                    d.DepartmentName
                FROM Users u
                JOIN Roles r ON u.RoleID = r.RoleID
                JOIN Employees e ON u.EmployeeID = e.EmployeeID
                LEFT JOIN OrganizationalStructure d ON e.DepartmentID = d.DepartmentID
                WHERE u.UserID = :user_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all users with pagination
     */
    public function getUsers($page = 1, $limit = 20, $filters = []) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT
                    u.UserID, u.EmployeeID, u.Username, u.RoleID, u.IsActive,
                    r.RoleName,
                    e.FirstName, e.LastName, e.Email, e.JobTitle,
                    d.DepartmentName
                FROM Users u
                JOIN Roles r ON u.RoleID = r.RoleID
                JOIN Employees e ON u.EmployeeID = e.EmployeeID
                LEFT JOIN OrganizationalStructure d ON e.DepartmentID = d.DepartmentID
                WHERE 1=1";

        $params = [];

        // Apply filters
        if (!empty($filters['role_id'])) {
            $sql .= " AND u.RoleID = :role_id";
            $params[':role_id'] = $filters['role_id'];
        }

        if (!empty($filters['is_active'])) {
            $sql .= " AND u.IsActive = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (u.Username LIKE :search OR e.FirstName LIKE :search OR e.LastName LIKE :search OR e.Email LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY u.Username LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count total users
     */
    public function countUsers($filters = []) {
        $sql = "SELECT COUNT(*) as total
                FROM Users u
                JOIN Employees e ON u.EmployeeID = e.EmployeeID
                WHERE 1=1";

        $params = [];

        // Apply same filters as getUsers
        if (!empty($filters['role_id'])) {
            $sql .= " AND u.RoleID = :role_id";
            $params[':role_id'] = $filters['role_id'];
        }

        if (!empty($filters['is_active'])) {
            $sql .= " AND u.IsActive = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (u.Username LIKE :search OR e.FirstName LIKE :search OR e.LastName LIKE :search OR e.Email LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)$result['total'];
    }

    /**
     * Create new user
     */
    public function createUser($data) {
        $sql = "INSERT INTO Users (EmployeeID, Username, PasswordHash, RoleID, IsActive, IsTwoFactorEnabled)
                VALUES (:employee_id, :username, :password_hash, :role_id, :is_active, :two_factor_enabled)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':employee_id', $data['employee_id'], PDO::PARAM_INT);
        $stmt->bindParam(':username', $data['username'], PDO::PARAM_STR);
        $stmt->bindParam(':password_hash', $data['password_hash'], PDO::PARAM_STR);
        $stmt->bindParam(':role_id', $data['role_id'], PDO::PARAM_INT);
        $stmt->bindParam(':is_active', $data['is_active'], PDO::PARAM_INT);
        $stmt->bindParam(':two_factor_enabled', $data['two_factor_enabled'], PDO::PARAM_INT);
        
        $stmt->execute();
        return $this->pdo->lastInsertId();
    }

    /**
     * Update user
     */
    public function updateUser($userId, $data) {
        $sql = "UPDATE Users SET 
                    Username = :username,
                    RoleID = :role_id,
                    IsActive = :is_active,
                    IsTwoFactorEnabled = :two_factor_enabled
                WHERE UserID = :user_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':username', $data['username'], PDO::PARAM_STR);
        $stmt->bindParam(':role_id', $data['role_id'], PDO::PARAM_INT);
        $stmt->bindParam(':is_active', $data['is_active'], PDO::PARAM_INT);
        $stmt->bindParam(':two_factor_enabled', $data['two_factor_enabled'], PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Update user password
     */
    public function updatePassword($userId, $newPassword) {
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        
        $sql = "UPDATE Users SET PasswordHash = :password_hash WHERE UserID = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':password_hash', $passwordHash, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Delete user
     */
    public function deleteUser($userId) {
        $sql = "UPDATE Users SET IsActive = 0 WHERE UserID = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Store 2FA code
     */
    public function store2FACode($userId, $code, $expiry) {
        $sql = "UPDATE Users SET 
                    TwoFactorEmailCode = :code,
                    TwoFactorCodeExpiry = :expiry
                WHERE UserID = :user_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':code', $code, PDO::PARAM_STR);
        $stmt->bindParam(':expiry', $expiry, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Verify 2FA code
     */
    public function verify2FACode($userId, $code) {
        $sql = "SELECT
                    u.UserID, u.EmployeeID, u.Username, u.RoleID, u.IsActive,
                    r.RoleName,
                    e.FirstName, e.LastName, e.Email
                FROM Users u
                JOIN Roles r ON u.RoleID = r.RoleID
                JOIN Employees e ON u.EmployeeID = e.EmployeeID
                WHERE u.UserID = :user_id 
                AND u.TwoFactorEmailCode = :code
                AND u.TwoFactorCodeExpiry > NOW()
                AND u.IsActive = 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':code', $code, PDO::PARAM_STR);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Clear the 2FA code after successful verification
            $this->clear2FACode($userId);
        }
        
        return $user;
    }

    /**
     * Clear 2FA code
     */
    private function clear2FACode($userId) {
        $sql = "UPDATE Users SET 
                    TwoFactorEmailCode = NULL,
                    TwoFactorCodeExpiry = NULL
                WHERE UserID = :user_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Store password reset token
     */
    public function storeResetToken($userId, $token, $expiry) {
        $sql = "UPDATE Users SET 
                    ResetToken = :token,
                    ResetTokenExpiry = :expiry
                WHERE UserID = :user_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':token', $token, PDO::PARAM_STR);
        $stmt->bindParam(':expiry', $expiry, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Update last login
     */
    public function updateLastLogin($userId) {
        $sql = "UPDATE Users SET LastLogin = NOW() WHERE UserID = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Check if username exists
     */
    public function usernameExists($username, $excludeUserId = null) {
        $sql = "SELECT UserID FROM Users WHERE Username = :username";
        
        if ($excludeUserId) {
            $sql .= " AND UserID != :exclude_user_id";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        
        if ($excludeUserId) {
            $stmt->bindParam(':exclude_user_id', $excludeUserId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetch() !== false;
    }
}

<?php
/**
 * Users Routes
 * Handles user management operations
 */

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Request.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../models/User.php';

class UsersController {
    private $pdo;
    private $authMiddleware;
    private $userModel;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->authMiddleware = new AuthMiddleware();
        $this->userModel = new User();
    }

    /**
     * Handle user requests
     */
    public function handleRequest($method, $id = null, $subResource = null) {
        switch ($method) {
            case 'GET':
                if ($id === null) {
                    $this->getUsers();
                } else {
                    $this->getUser($id);
                }
                break;
            case 'POST':
                $this->createUser();
                break;
            case 'PUT':
            case 'PATCH':
                if ($id === null) {
                    Response::methodNotAllowed();
                } else {
                    $this->updateUser($id);
                }
                break;
            case 'DELETE':
                if ($id === null) {
                    Response::methodNotAllowed();
                } else {
                    $this->deleteUser($id);
                }
                break;
            default:
                Response::methodNotAllowed();
        }
    }

    /**
     * Get all users
     */
    private function getUsers() {
        $request = new Request();
        $pagination = $request->getPagination();
        
        $filters = [
            'role_id' => $request->getData('role_id'),
            'is_active' => $request->getData('is_active'),
            'search' => $request->getData('search')
        ];

        // Remove empty filters
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            $users = $this->userModel->getUsers(
                $pagination['page'],
                $pagination['limit'],
                $filters
            );

            $total = $this->userModel->countUsers($filters);
            $totalPages = ceil($total / $pagination['limit']);

            $paginationData = [
                'current_page' => $pagination['page'],
                'per_page' => $pagination['limit'],
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $pagination['page'] < $totalPages,
                'has_prev' => $pagination['page'] > 1
            ];

            Response::paginated($users, $paginationData);

        } catch (Exception $e) {
            error_log("Get users error: " . $e->getMessage());
            Response::error('Failed to retrieve users', 500);
        }
    }

    /**
     * Get single user
     */
    private function getUser($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid user ID']);
        }

        try {
            $user = $this->userModel->getUserById($id);
            
            if (!$user) {
                Response::notFound('User not found');
            }

            Response::success($user);

        } catch (Exception $e) {
            error_log("Get user error: " . $e->getMessage());
            Response::error('Failed to retrieve user', 500);
        }
    }

    /**
     * Create new user
     */
    private function createUser() {
        // Check authorization - only admins can create users
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to create users');
        }

        $request = new Request();
        $data = $request->getData();

        // Validate required fields
        $errors = $request->validateRequired([
            'employee_id', 'username', 'password', 'role_id'
        ]);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        // Additional validation
        $username = $request->sanitizeString($data['username']);
        $password = $data['password'];
        $employeeId = (int)$data['employee_id'];
        $roleId = (int)$data['role_id'];
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : 1;
        $twoFactorEnabled = isset($data['two_factor_enabled']) ? (int)$data['two_factor_enabled'] : 0;

        // Validate password strength
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long';
        }

        // Check if username already exists
        if ($this->userModel->usernameExists($username)) {
            $errors['username'] = 'Username already exists';
        }

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        try {
            $userData = [
                'employee_id' => $employeeId,
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'role_id' => $roleId,
                'is_active' => $isActive,
                'two_factor_enabled' => $twoFactorEnabled
            ];

            $userId = $this->userModel->createUser($userData);

            Response::created([
                'user_id' => $userId,
                'username' => $username
            ], 'User created successfully');

        } catch (Exception $e) {
            error_log("Create user error: " . $e->getMessage());
            Response::error('Failed to create user', 500);
        }
    }

    /**
     * Update user
     */
    private function updateUser($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid user ID']);
        }

        // Check authorization - users can update their own profile, admins can update any
        $currentUser = $this->authMiddleware->getCurrentUser();
        if ($currentUser['user_id'] != $id && 
            !$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to update this user');
        }

        $request = new Request();
        $data = $request->getData();

        // Check if user exists
        $existingUser = $this->userModel->getUserById($id);
        if (!$existingUser) {
            Response::notFound('User not found');
        }

        $errors = [];

        // Validate username if provided
        if (isset($data['username'])) {
            $username = $request->sanitizeString($data['username']);
            if ($this->userModel->usernameExists($username, $id)) {
                $errors['username'] = 'Username already exists';
            }
        }

        // Validate password if provided
        if (isset($data['password'])) {
            if (strlen($data['password']) < 8) {
                $errors['password'] = 'Password must be at least 8 characters long';
            }
        }

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        try {
            $updateData = [];

            if (isset($data['username'])) {
                $updateData['username'] = $request->sanitizeString($data['username']);
            }

            if (isset($data['role_id'])) {
                $updateData['role_id'] = (int)$data['role_id'];
            }

            if (isset($data['is_active'])) {
                $updateData['is_active'] = (int)$data['is_active'];
            }

            if (isset($data['two_factor_enabled'])) {
                $updateData['two_factor_enabled'] = (int)$data['two_factor_enabled'];
            }

            if (!empty($updateData)) {
                $this->userModel->updateUser($id, $updateData);
            }

            // Update password separately if provided
            if (isset($data['password'])) {
                $this->userModel->updatePassword($id, $data['password']);
            }

            Response::success(null, 'User updated successfully');

        } catch (Exception $e) {
            error_log("Update user error: " . $e->getMessage());
            Response::error('Failed to update user', 500);
        }
    }

    /**
     * Delete user (soft delete)
     */
    private function deleteUser($id) {
        $request = new Request();
        if (!$request->validateInteger($id)) {
            Response::validationError(['id' => 'Invalid user ID']);
        }

        // Check authorization - only admins can delete users
        if (!$this->authMiddleware->hasAnyRole(['System Admin', 'HR Manager'])) {
            Response::forbidden('Insufficient permissions to delete users');
        }

        try {
            $user = $this->userModel->getUserById($id);
            
            if (!$user) {
                Response::notFound('User not found');
            }

            $this->userModel->deleteUser($id);

            Response::success(null, 'User deleted successfully');

        } catch (Exception $e) {
            error_log("Delete user error: " . $e->getMessage());
            Response::error('Failed to delete user', 500);
        }
    }
}

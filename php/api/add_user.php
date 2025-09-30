<?php
/**
 * API Endpoint: Add User
 * Creates a new user account linked to an employee.
 */

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log');

session_start(); // Needed for authorization check

// Check if user is authenticated
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required. Please log in.']);
    exit();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Database Connection ---
$pdo = null;
try {
    require_once '../db_connect.php';
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('DB connection failed');
    }
} catch (Throwable $e) {
    error_log("PHP Error in add_user.php (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Server configuration error.']);
    exit;
}

// --- Authorization Check ---
// Only allow System Admins to add users
$allowed_roles = [1]; // RoleID 1 = System Admin
if (!isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], $allowed_roles)) {
     http_response_code(403); // Forbidden
     echo json_encode(['error' => 'Permission denied. You do not have rights to add users.']);
     exit;
}
// --- End Auth Check ---

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'POST method required.']);
    exit;
}

// --- Get Data from POST Request (expecting JSON) ---
$input_data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload received.']);
    exit;
}

// --- Extract and sanitize data ---
$employee_id = isset($input_data['employee_id']) ? filter_var($input_data['employee_id'], FILTER_VALIDATE_INT) : null;
$username = isset($input_data['username']) ? trim(htmlspecialchars($input_data['username'])) : null;
$role_id = isset($input_data['role_id']) ? filter_var($input_data['role_id'], FILTER_VALIDATE_INT) : null;
$password = isset($input_data['password']) ? $input_data['password'] : null; // Get raw password
$is_active_input = isset($input_data['is_active']) ? filter_var($input_data['is_active'], FILTER_VALIDATE_INT) : null;

// --- Validate Input ---
$errors = [];
if (empty($employee_id) || $employee_id <= 0) $errors['employee_id'] = 'Valid Employee ID is required.';
if (empty($username)) $errors['username'] = 'Username is required.';
if (empty($role_id) || $role_id <= 0) $errors['role_id'] = 'Valid Role ID is required.';
if (empty($password)) $errors['password'] = 'Password is required for new users.';
// Validate is_active is 0 or 1
if ($is_active_input === null || ($is_active_input !== 0 && $is_active_input !== 1)) {
    $errors['is_active'] = 'Is Active must be 0 or 1.';
    $is_active_db = 1; // Default to active if invalid
} else {
    $is_active_db = $is_active_input;
}

// Check username length, complexity etc. if needed
// Check password complexity if needed

// Check if username or employee already has a user account
if (empty($errors)) {
     try {
        $checkSql = "SELECT UserID FROM Users WHERE Username = :username OR EmployeeID = :employee_id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindParam(':username', $username, PDO::PARAM_STR);
        $checkStmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
        $checkStmt->execute();
        if ($checkStmt->fetch()) {
             $errors['duplicate'] = 'Username or Employee already has an associated user account.';
        }
    } catch (\PDOException $e) {
         error_log("API Error (add_user - Duplicate Check): " . $e->getMessage());
         $errors['database'] = 'Error checking for existing user.';
    }
}


if (!empty($errors)) {
    http_response_code(400); // Bad Request or 409 Conflict for duplicate
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}
// --- End Validation ---

// --- Hash Password ---
$password_hash = password_hash($password, PASSWORD_BCRYPT);
if ($password_hash === false) {
    error_log("Password hashing failed for username: " . $username);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to process password.']);
    exit;
}
// --- End Hash Password ---

// --- Insert into Database ---
try {
    $sql = "INSERT INTO Users (EmployeeID, Username, PasswordHash, RoleID, IsActive)
            VALUES (:employee_id, :username, :password_hash, :role_id, :is_active)";
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->bindParam(':password_hash', $password_hash, PDO::PARAM_STR);
    $stmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
    $stmt->bindParam(':is_active', $is_active_db, PDO::PARAM_INT);

    $stmt->execute();
    $new_user_id = $pdo->lastInsertId();

    // Success response
    http_response_code(201); // Created
    echo json_encode([
        'message' => 'User added successfully.',
        'user_id' => $new_user_id
    ]);

} catch (\PDOException $e) {
    error_log("PHP PDOException in add_user.php: " . $e->getMessage());
    if ($e->getCode() == '23000') { // Integrity constraint violation
         http_response_code(400); // Bad request (e.g., invalid EmployeeID/RoleID) or 409 Conflict (duplicate username/employee)
         echo json_encode(['error' => 'Failed to add user. Ensure Employee/Role IDs are valid and Username/Employee is unique.']);
    } else {
         http_response_code(500);
         echo json_encode(['error' => 'Database error adding user.']);
    }
} catch (Throwable $e) {
    error_log("PHP Throwable in add_user.php: " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Unexpected server error adding user.']);
}
exit;
?>

<?php
/**
 * API Endpoint: Add Employee and User
 * Creates a new employee record and a corresponding user account.
 */

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log');

session_start(); // Needed for authorization check

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
    error_log("PHP Error in add_employee_and_user.php (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Server configuration error.']);
    exit;
}

// --- Authorization Check ---
// Only allow System Admins to add employees and users
$allowed_roles = [1]; // RoleID 1 = System Admin
if (!isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], $allowed_roles)) {
     http_response_code(403); // Forbidden
     echo json_encode(['error' => 'Permission denied. You do not have rights to add employees/users.']);
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

// --- Extract and sanitize employee data ---
$first_name = isset($input_data['first_name']) ? trim(htmlspecialchars($input_data['first_name'])) : null;
$last_name = isset($input_data['last_name']) ? trim(htmlspecialchars($input_data['last_name'])) : null;
$email = isset($input_data['email']) ? filter_var(trim($input_data['email']), FILTER_VALIDATE_EMAIL) : null;
$job_title = isset($input_data['job_title']) ? trim(htmlspecialchars($input_data['job_title'])) : null;
$department_id = isset($input_data['department_id']) ? filter_var($input_data['department_id'], FILTER_VALIDATE_INT) : null;
$hire_date = isset($input_data['hire_date']) ? $input_data['hire_date'] : null; // Optional, YYYY-MM-DD

// --- Extract and sanitize user data ---
$username = isset($input_data['username']) ? trim(htmlspecialchars($input_data['username'])) : null;
$role_id = isset($input_data['role_id']) ? filter_var($input_data['role_id'], FILTER_VALIDATE_INT) : null;
$password = isset($input_data['password']) ? $input_data['password'] : null; // Get raw password
$is_active_input = isset($input_data['is_active']) ? filter_var($input_data['is_active'], FILTER_VALIDATE_INT) : 1; // Default to active

// --- Validate Input ---
$errors = [];
// Employee validation
if (empty($first_name)) $errors['first_name'] = 'First Name is required.';
if (empty($last_name)) $errors['last_name'] = 'Last Name is required.';
if (empty($email)) $errors['email'] = 'A valid Email is required.';
if (empty($job_title)) $errors['job_title'] = 'Job Title is required.';
if (empty($department_id) || $department_id <=0) $errors['department_id'] = 'Valid Department ID is required.';
if (!empty($hire_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hire_date)) {
    $errors['hire_date'] = 'Hire Date must be in YYYY-MM-DD format if provided.';
} else if (empty($hire_date)) {
    $hire_date = null; // Set to null if not provided
}


// User validation
if (empty($username)) $errors['username'] = 'Username is required.';
if (empty($role_id) || $role_id <= 0) $errors['role_id'] = 'Valid Role ID is required.';
if (empty($password)) $errors['password'] = 'Password is required for new users.';
if ($is_active_input === null || ($is_active_input !== 0 && $is_active_input !== 1)) {
    $errors['is_active'] = 'Is Active must be 0 or 1.';
    $is_active_db = 1; // Default to active if invalid
} else {
    $is_active_db = $is_active_input;
}

// Check if username or email already exists for a user or employee
if (empty($errors)) {
     try {
        $checkUserSql = "SELECT UserID FROM Users WHERE Username = :username";
        $checkUserStmt = $pdo->prepare($checkUserSql);
        $checkUserStmt->bindParam(':username', $username, PDO::PARAM_STR);
        $checkUserStmt->execute();
        if ($checkUserStmt->fetch()) {
             $errors['username_duplicate'] = 'This username is already taken.';
        }

        $checkEmailSql = "SELECT EmployeeID FROM Employees WHERE Email = :email";
        $checkEmailStmt = $pdo->prepare($checkEmailSql);
        $checkEmailStmt->bindParam(':email', $email, PDO::PARAM_STR);
        $checkEmailStmt->execute();
        if ($checkEmailStmt->fetch()) {
             $errors['email_duplicate'] = 'This email address is already registered for an employee.';
        }

    } catch (\PDOException $e) {
         error_log("API Error (add_employee_and_user - Duplicate Check): " . $e->getMessage());
         $errors['database_check'] = 'Error checking for existing user or email.';
    }
}


if (!empty($errors)) {
    http_response_code(400); // Bad Request
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

// --- Database Transaction ---
try {
    $pdo->beginTransaction();

    // 1. Insert into Employees table
    $sql_employee = "INSERT INTO Employees (FirstName, LastName, Email, JobTitle, DepartmentID, HireDate, IsActive)
                     VALUES (:first_name, :last_name, :email, :job_title, :department_id, :hire_date, TRUE)"; // New employees are active by default
    $stmt_employee = $pdo->prepare($sql_employee);
    $stmt_employee->bindParam(':first_name', $first_name, PDO::PARAM_STR);
    $stmt_employee->bindParam(':last_name', $last_name, PDO::PARAM_STR);
    $stmt_employee->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt_employee->bindParam(':job_title', $job_title, PDO::PARAM_STR);
    $stmt_employee->bindParam(':department_id', $department_id, PDO::PARAM_INT);
    $stmt_employee->bindParam(':hire_date', $hire_date, $hire_date === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt_employee->execute();
    $new_employee_id = $pdo->lastInsertId();

    if (!$new_employee_id) {
        throw new Exception("Failed to create employee record.");
    }

    // 2. Insert into Users table
    $sql_user = "INSERT INTO Users (EmployeeID, Username, PasswordHash, RoleID, IsActive)
                 VALUES (:employee_id, :username, :password_hash, :role_id, :is_active)";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->bindParam(':employee_id', $new_employee_id, PDO::PARAM_INT);
    $stmt_user->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt_user->bindParam(':password_hash', $password_hash, PDO::PARAM_STR);
    $stmt_user->bindParam(':role_id', $role_id, PDO::PARAM_INT);
    $stmt_user->bindParam(':is_active', $is_active_db, PDO::PARAM_INT);
    $stmt_user->execute();
    $new_user_id = $pdo->lastInsertId();

    if (!$new_user_id) {
        throw new Exception("Failed to create user account after creating employee record.");
    }

    $pdo->commit(); // Commit both inserts

    // Success response
    http_response_code(201); // Created
    echo json_encode([
        'message' => 'Employee and User account created successfully.',
        'employee_id' => $new_employee_id,
        'user_id' => $new_user_id
    ]);

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("PHP PDOException in add_employee_and_user.php: " . $e->getMessage());
    if ($e->getCode() == '23000') { // Integrity constraint violation
         http_response_code(400);
         echo json_encode(['error' => 'Failed to add employee/user. Ensure Department/Role IDs are valid or data is unique.']);
    } else {
         http_response_code(500);
         echo json_encode(['error' => 'Database error creating employee or user.']);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("PHP Throwable in add_employee_and_user.php: " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Unexpected server error creating employee or user.']);
}
exit;
?>

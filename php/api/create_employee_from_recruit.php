<?php
/**
 * API Endpoint: Create Employee from Recruit
 * Receives data from HR 1-2 system for a new hire and creates corresponding
 * Employee and User records in the HR 3-4 database.
 */

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep 0 for production, 1 for debugging
ini_set('log_errors', 1);
// Ensure this path is correct and writable by the web server
// ini_set('error_log', __DIR__ . '/../../php-error.log');

session_start(); // Needed for potential authorization if this endpoint is protected

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production (e.g., HR 1-2 system's domain)
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Add Authorization if you use it
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Database Connection ---
$pdo = null;
try {
    require_once '../db_connect.php'; // Path relative to this api script
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('Database connection object ($pdo) not properly created by db_connect.php.');
    }
} catch (Throwable $e) {
    error_log("PHP Error in create_employee_from_recruit.php (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Server configuration error: Could not connect to the database.']);
    exit;
}

// --- Authorization Check (Example - Adapt as needed) ---
// This endpoint might be called by another system (HR 1-2).
// You might use an API key, OAuth, or session-based auth if called from a trusted frontend.
// For simplicity, this example assumes a basic check or an API key passed in headers.
/*
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null; // Example: HR 1-2 system sends an API key
$expectedApiKey = 'YOUR_SECRET_API_KEY_FOR_HR12_TO_HR34_COMMUNICATION'; // Store this securely

if ($apiKey !== $expectedApiKey) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Invalid or missing API key.']);
    exit;
}
*/
// Or, if HR 3-4 admin users trigger this via UI after HR 1-2 process:
/*
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) { // SysAdmin or HR Admin
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied to create new employees.']);
    exit;
}
*/
// --- End Authorization Check ---

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
    echo json_encode(['error' => 'Invalid JSON payload received. Error: ' . json_last_error_msg()]);
    exit;
}

// --- Extract and sanitize data ---
// Employee Data (from HR 1-2, to be inserted into HR 3-4 Employees table)
$first_name = isset($input_data['first_name']) ? trim(htmlspecialchars($input_data['first_name'])) : null;
$last_name = isset($input_data['last_name']) ? trim(htmlspecialchars($input_data['last_name'])) : null;
$email = isset($input_data['email']) ? filter_var(trim($input_data['email']), FILTER_VALIDATE_EMAIL) : null;
$phone_number = isset($input_data['phone_number']) ? trim(htmlspecialchars($input_data['phone_number'])) : null;
$date_of_birth = isset($input_data['date_of_birth']) ? $input_data['date_of_birth'] : null; // Expect YYYY-MM-DD
$hire_date = isset($input_data['hire_date']) ? $input_data['hire_date'] : null; // Expect YYYY-MM-DD
$job_title = isset($input_data['job_title']) ? trim(htmlspecialchars($input_data['job_title'])) : null; // This might come from HR12_JobRoles.RoleName
$department_id = isset($input_data['department_id']) ? filter_var($input_data['department_id'], FILTER_VALIDATE_INT) : null; // This should be an ID from HR34.OrganizationalStructure
$hr12_job_role_id = isset($input_data['hr12_job_role_id']) ? filter_var($input_data['hr12_job_role_id'], FILTER_VALIDATE_INT) : null; // ID from HR12_JobRoles for reference

// User Data (for HR 3-4 Users table)
$username = isset($input_data['username']) ? trim(htmlspecialchars($input_data['username'])) : null;
$password = isset($input_data['password']) ? $input_data['password'] : null; // Temporary password
$hr34_role_id = isset($input_data['hr34_role_id']) ? filter_var($input_data['hr34_role_id'], FILTER_VALIDATE_INT) : null; // RoleID from HR34.Roles
$user_is_active = isset($input_data['user_is_active']) ? filter_var($input_data['user_is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : true;

// Initial Salary Data (for HR 3-4 EmployeeSalaries table)
$initial_base_salary = isset($input_data['initial_base_salary']) ? filter_var($input_data['initial_base_salary'], FILTER_VALIDATE_FLOAT) : null;
$initial_pay_frequency = isset($input_data['initial_pay_frequency']) ? trim(htmlspecialchars($input_data['initial_pay_frequency'])) : 'Monthly'; // Default
$initial_pay_rate = isset($input_data['initial_pay_rate']) ? filter_var($input_data['initial_pay_rate'], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null;
$salary_effective_date = $hire_date; // Salary usually effective from hire date

// --- Validate Input ---
$errors = [];
// Employee validation
if (empty($first_name)) $errors['first_name'] = 'First Name is required.';
if (empty($last_name)) $errors['last_name'] = 'Last Name is required.';
if (empty($email)) $errors['email'] = 'A valid Email is required.';
if (empty($job_title)) $errors['job_title'] = 'Job Title is required.';
if (empty($department_id) || $department_id <= 0) $errors['department_id'] = 'Valid Department ID (from HR 3-4 system) is required.';
if (empty($hire_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hire_date)) {
    $errors['hire_date'] = 'Valid Hire Date (YYYY-MM-DD) is required.';
}
if (!empty($date_of_birth) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_birth)) {
    $errors['date_of_birth'] = 'Date of Birth must be in YYYY-MM-DD format if provided.';
} elseif (empty($date_of_birth)) {
    $date_of_birth = null;
}


// User validation
if (empty($username)) $errors['username'] = 'Username is required.';
if (empty($password)) $errors['password'] = 'A temporary password is required.';
if (empty($hr34_role_id) || $hr34_role_id <= 0) $errors['hr34_role_id'] = 'Valid Role ID (from HR 3-4 system) for the user is required.';
if ($user_is_active === null) $user_is_active = true; // Default to active

// Salary validation
if ($initial_base_salary === null || $initial_base_salary < 0) $errors['initial_base_salary'] = 'Valid Initial Base Salary (non-negative) is required.';
if (empty($initial_pay_frequency)) $errors['initial_pay_frequency'] = 'Initial Pay Frequency is required.';
if ($initial_pay_rate !== null && $initial_pay_rate < 0) $errors['initial_pay_rate'] = 'Initial Pay Rate must be non-negative if provided.';


// Check for existing email in Employees or username in Users (in HR 3-4 DB)
if (empty($errors)) {
    try {
        $stmt_check_email = $pdo->prepare("SELECT EmployeeID FROM Employees WHERE Email = :email");
        $stmt_check_email->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt_check_email->execute();
        if ($stmt_check_email->fetch()) {
            $errors['email_duplicate'] = 'This email address is already registered for an employee in HR 3-4.';
        }

        $stmt_check_username = $pdo->prepare("SELECT UserID FROM Users WHERE Username = :username");
        $stmt_check_username->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt_check_username->execute();
        if ($stmt_check_username->fetch()) {
            $errors['username_duplicate'] = 'This username is already taken in HR 3-4.';
        }
    } catch (\PDOException $e) {
        error_log("API Error (create_employee_from_recruit - Duplicate Check): " . $e->getMessage());
        $errors['database_check'] = 'Error checking for existing employee/user.';
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

    // 1. Insert into HR 3-4 Employees table
    $sql_employee = "INSERT INTO Employees (FirstName, LastName, Email, PhoneNumber, DateOfBirth, HireDate, JobTitle, DepartmentID, IsActive)
                     VALUES (:first_name, :last_name, :email, :phone_number, :date_of_birth, :hire_date, :job_title, :department_id, TRUE)";
    $stmt_employee = $pdo->prepare($sql_employee);
    $stmt_employee->bindParam(':first_name', $first_name, PDO::PARAM_STR);
    $stmt_employee->bindParam(':last_name', $last_name, PDO::PARAM_STR);
    $stmt_employee->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt_employee->bindParam(':phone_number', $phone_number, PDO::PARAM_STR);
    $stmt_employee->bindParam(':date_of_birth', $date_of_birth, $date_of_birth === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt_employee->bindParam(':hire_date', $hire_date, PDO::PARAM_STR);
    $stmt_employee->bindParam(':job_title', $job_title, PDO::PARAM_STR);
    $stmt_employee->bindParam(':department_id', $department_id, PDO::PARAM_INT);
    // Note: HR12_JobRoleID is not directly inserted here unless your HR34.Employees table has such a field.
    // It's more for context or if you have a mapping.
    $stmt_employee->execute();
    $new_hr34_employee_id = $pdo->lastInsertId();

    if (!$new_hr34_employee_id) {
        throw new Exception("Failed to create employee record in HR 3-4 database.");
    }

    // 2. Insert into HR 3-4 Users table
    $sql_user = "INSERT INTO Users (EmployeeID, Username, PasswordHash, RoleID, IsActive)
                 VALUES (:employee_id, :username, :password_hash, :role_id, :is_active)";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->bindParam(':employee_id', $new_hr34_employee_id, PDO::PARAM_INT);
    $stmt_user->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt_user->bindParam(':password_hash', $password_hash, PDO::PARAM_STR);
    $stmt_user->bindParam(':role_id', $hr34_role_id, PDO::PARAM_INT);
    $stmt_user->bindParam(':is_active', $user_is_active, PDO::PARAM_BOOL);
    $stmt_user->execute();
    $new_hr34_user_id = $pdo->lastInsertId();

    if (!$new_hr34_user_id) {
        throw new Exception("Failed to create user account in HR 3-4 database after creating employee record.");
    }

    // 3. Insert initial salary into HR 3-4 EmployeeSalaries table
    if ($initial_base_salary !== null) {
        $sql_salary = "INSERT INTO EmployeeSalaries (EmployeeID, BaseSalary, PayFrequency, PayRate, EffectiveDate, IsCurrent)
                       VALUES (:employee_id, :base_salary, :pay_frequency, :pay_rate, :effective_date, TRUE)";
        $stmt_salary = $pdo->prepare($sql_salary);
        $stmt_salary->bindParam(':employee_id', $new_hr34_employee_id, PDO::PARAM_INT);
        $stmt_salary->bindParam(':base_salary', $initial_base_salary);
        $stmt_salary->bindParam(':pay_frequency', $initial_pay_frequency, PDO::PARAM_STR);
        $stmt_salary->bindValue(':pay_rate', $initial_pay_rate, $initial_pay_rate === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt_salary->bindParam(':effective_date', $salary_effective_date, PDO::PARAM_STR);
        $stmt_salary->execute();
        if ($stmt_salary->rowCount() == 0) {
            throw new Exception("Failed to insert initial salary record for the new employee.");
        }
    }

    $pdo->commit(); // Commit all inserts

    // Success response
    http_response_code(201); // Created
    echo json_encode([
        'message' => 'Employee and User account created successfully in HR 3-4 system.',
        'hr34_employee_id' => $new_hr34_employee_id,
        'hr34_user_id' => $new_hr34_user_id
    ]);

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("PHP PDOException in create_employee_from_recruit.php: " . $e->getMessage());
    if ($e->getCode() == '23000') { // Integrity constraint violation
         http_response_code(400); // Or 409 Conflict
         echo json_encode(['error' => 'Failed to create employee/user. Data conflict or invalid foreign key (e.g., DepartmentID, RoleID).', 'details' => $e->getMessage()]);
    } else {
         http_response_code(500);
         echo json_encode(['error' => 'Database error creating employee or user in HR 3-4.']);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("PHP Throwable in create_employee_from_recruit.php: " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Unexpected server error creating employee or user in HR 3-4.']);
}
exit;
?>

<?php
/**
 * API Endpoint: Verify 2FA Code
 * Verifies the email code submitted by the user and completes login if valid.
 */

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log');

session_start(); // Start session to potentially store final login state

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

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
    error_log("PHP Error in verify_2fa.php (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Server configuration error.']);
    exit;
}

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
$user_id = isset($input_data['user_id']) ? filter_var($input_data['user_id'], FILTER_VALIDATE_INT) : null; // Get UserID passed from login step
$submitted_code = isset($input_data['code']) ? trim(htmlspecialchars($input_data['code'])) : null;

// --- Validate Input ---
$errors = [];
if (empty($user_id) || $user_id <= 0) $errors['user_id'] = 'User ID is required.';
if (empty($submitted_code) || !ctype_digit($submitted_code) || strlen($submitted_code) !== 6) { // Assuming 6-digit code
    $errors['code'] = 'A valid 6-digit code is required.';
}

if (!empty($errors)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}
// --- End Validation ---

// --- Verification Logic ---
try {
    // Fetch the stored code and expiry for the user, along with other necessary details for session
    $sql_fetch = "SELECT u.UserID, u.EmployeeID, u.Username, u.RoleID, u.IsActive,
                         u.TwoFactorEmailCode, u.TwoFactorCodeExpiry,
                         r.RoleName, e.FirstName, e.LastName
                  FROM Users u
                  JOIN Roles r ON u.RoleID = r.RoleID
                  JOIN Employees e ON u.EmployeeID = e.EmployeeID
                  WHERE u.UserID = :user_id";
    $stmt_fetch = $pdo->prepare($sql_fetch);
    $stmt_fetch->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_fetch->execute();
    $user = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'User not found.']);
        exit;
    }

    // Check if code exists, matches, and hasn't expired
    $stored_code = $user['TwoFactorEmailCode'];
    $expiry_timestamp_str = $user['TwoFactorCodeExpiry'];
    $is_code_valid = false;

    if (!empty($stored_code) && !empty($expiry_timestamp_str)) {
        $expiry_datetime = new DateTime($expiry_timestamp_str);
        $now_datetime = new DateTime(); // Current time

        // Direct comparison for plain text code. If hashing codes, use hash_equals.
        if ($submitted_code === $stored_code && $now_datetime < $expiry_datetime) {
            $is_code_valid = true;
        }
    }

    // --- Clear the used/expired code from DB regardless of validity ---
    // This prevents replay attacks with the same code.
    $sql_clear_code = "UPDATE Users
                       SET TwoFactorEmailCode = NULL, TwoFactorCodeExpiry = NULL
                       WHERE UserID = :user_id";
    $stmt_clear_code = $pdo->prepare($sql_clear_code);
    $stmt_clear_code->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_clear_code->execute(); // Execute clearing

    // --- Handle Verification Result ---
    if ($is_code_valid) {
        // Code is valid - Complete the login process
        session_regenerate_id(true); // Regenerate session ID for security

        // Store user information in session
        $_SESSION['user_id'] = $user['UserID'];
        $_SESSION['employee_id'] = $user['EmployeeID'];
        $_SESSION['username'] = $user['Username'];
        $_SESSION['role_id'] = $user['RoleID'];
        $_SESSION['role_name'] = $user['RoleName'];
        $_SESSION['full_name'] = $user['FirstName'] . ' ' . $user['LastName'];

        http_response_code(200);
        echo json_encode([
            'message' => 'Login successful.',
            'user' => [ // Send user details for UI update
                'user_id' => $user['UserID'],
                'employee_id' => $user['EmployeeID'],
                'username' => $user['Username'],
                'full_name' => $_SESSION['full_name'],
                'role_name' => $user['RoleName']
            ]
        ]);
        exit;

    } else {
        // Code is invalid or expired
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Invalid or expired authentication code.']);
        exit;
    }

} catch (\PDOException $e) {
    error_log("PHP PDOException in verify_2fa.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error during verification.']);
} catch (\Exception $e) { // Catch exceptions from DateTime
     error_log("PHP Exception in verify_2fa.php: " . $e->getMessage());
     http_response_code(500);
     echo json_encode(['error' => 'An internal error occurred during verification.']);
} catch (Throwable $e) {
    error_log("PHP Throwable in verify_2fa.php: " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Unexpected server error during verification.']);
}
exit;
?>

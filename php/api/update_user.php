<?php
/**
 * API Endpoint: Update User
 * Updates a user's role and active status.
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
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Use POST for simplicity
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
    error_log("PHP Error in update_user.php (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Server configuration error.']);
    exit;
}

// --- Authorization Check ---
// Only allow System Admins to update users
$allowed_roles = [1]; // RoleID 1 = System Admin
if (!isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], $allowed_roles)) {
     http_response_code(403); // Forbidden
     echo json_encode(['error' => 'Permission denied. You do not have rights to update users.']);
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
$user_id = isset($input_data['user_id']) ? filter_var($input_data['user_id'], FILTER_VALIDATE_INT) : null;
$role_id = isset($input_data['role_id']) ? filter_var($input_data['role_id'], FILTER_VALIDATE_INT) : null;
$is_active_input = isset($input_data['is_active']) ? filter_var($input_data['is_active'], FILTER_VALIDATE_INT) : null;

// --- Validate Input ---
$errors = [];
if (empty($user_id) || $user_id <= 0) $errors['user_id'] = 'Valid User ID is required for update.';
if (empty($role_id) || $role_id <= 0) $errors['role_id'] = 'Valid Role ID is required.';
// Validate is_active is 0 or 1
if ($is_active_input === null || ($is_active_input !== 0 && $is_active_input !== 1)) {
    $errors['is_active'] = 'Is Active must be 0 or 1.';
    $is_active_db = 1; // Default to active if invalid
} else {
    $is_active_db = $is_active_input;
}

// Prevent admin from deactivating their own account? (Optional safety check)
// if ($user_id == $_SESSION['user_id'] && $is_active_db == 0) {
//     $errors['self_deactivate'] = 'You cannot deactivate your own account.';
// }

if (!empty($errors)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}
// --- End Validation ---

// --- Update Database ---
try {
    $sql = "UPDATE Users
            SET RoleID = :role_id,
                IsActive = :is_active
            WHERE UserID = :user_id";
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
    $stmt->bindParam(':is_active', $is_active_db, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        // Success response
        http_response_code(200); // OK
        echo json_encode([
            'message' => 'User updated successfully.',
            'user_id' => $user_id
        ]);
    } else {
        // Check if ID existed but nothing changed, or if ID didn't exist
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM Users WHERE UserID = :id");
        $checkStmt->bindParam(':id', $user_id, PDO::PARAM_INT);
        $checkStmt->execute();
        if ($checkStmt->fetchColumn() > 0) {
             http_response_code(200); // OK
             echo json_encode(['message' => 'User details submitted, no changes detected.', 'user_id' => $user_id]);
        } else {
             http_response_code(404); // Not Found
             echo json_encode(['error' => 'User not found with the specified ID.']);
        }
    }

} catch (\PDOException $e) {
    error_log("PHP PDOException in update_user.php: " . $e->getMessage());
    if ($e->getCode() == '23000') { // Integrity constraint violation
         http_response_code(400);
         echo json_encode(['error' => 'Failed to update user. Ensure Role ID is valid.']);
    } else {
         http_response_code(500);
         echo json_encode(['error' => 'Database error updating user.']);
    }
} catch (Throwable $e) {
    error_log("PHP Throwable in update_user.php: " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Unexpected server error updating user.']);
}
exit;
?>

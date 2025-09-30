<?php
/**
 * API Endpoint: Change Password
 * Allows a logged-in user to change their own password.
 * Requires current password and, if 2FA is enabled, a valid 2FA code.
 */

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/../../php-error.log');

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
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
        throw new Exception('DB connection object not created.');
    }
} catch (Throwable $e) {
    error_log("Change Password API Error (DB Connection): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error. Could not connect to database.']);
    exit;
}

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required. Please log in.']);
    exit;
}
$loggedInUserId = $_SESSION['user_id'];

// --- Get Input Data ---
$input_data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload.']);
    exit;
}

$current_password = isset($input_data['current_password']) ? $input_data['current_password'] : null;
$new_password = isset($input_data['new_password']) ? $input_data['new_password'] : null;
$confirm_new_password = isset($input_data['confirm_new_password']) ? $input_data['confirm_new_password'] : null; // Though validation is frontend
$two_fa_code = isset($input_data['two_fa_code']) ? trim(htmlspecialchars($input_data['two_fa_code'])) : null;

// --- Validate Input ---
$errors = [];
if (empty($current_password)) $errors['current_password'] = 'Current password is required.';
if (empty($new_password)) {
    $errors['new_password'] = 'New password is required.';
} elseif (strlen($new_password) < 8) {
    $errors['new_password_length'] = 'New password must be at least 8 characters long.';
}
// Frontend should ensure new_password and confirm_new_password match, but a backend check is good too.
// if ($new_password !== $confirm_new_password) $errors['password_mismatch'] = 'New passwords do not match.';


if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}

// --- Process Password Change ---
try {
    // Fetch user's current password hash and 2FA status
    $sql_user = "SELECT UserID, PasswordHash, IsTwoFactorEnabled, TwoFactorEmailCode, TwoFactorCodeExpiry
                 FROM Users
                 WHERE UserID = :user_id";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->bindParam(':user_id', $loggedInUserId, PDO::PARAM_INT);
    $stmt_user->execute();
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404); // Should not happen if session is valid
        echo json_encode(['error' => 'User not found.']);
        exit;
    }

    // Verify current password
    if (!password_verify($current_password, trim($user['PasswordHash']))) {
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Incorrect current password.']);
        exit;
    }

    // Handle 2FA if enabled
    if ($user['IsTwoFactorEnabled']) {
        if (empty($two_fa_code)) {
            // This indicates the frontend should have prompted for it after calling request_2fa_code.php
            // Or, if the API is designed to prompt, this is where it would.
            // Given the current JS flow, we expect the code if 2FA is on.
            http_response_code(400);
            echo json_encode(['error' => '2FA code is required to change your password. Please request and enter the code.']);
            exit;
        }

        $stored_2fa_code = $user['TwoFactorEmailCode'];
        $expiry_timestamp_str = $user['TwoFactorCodeExpiry'];
        $is_2fa_code_valid = false;

        if (!empty($stored_2fa_code) && !empty($expiry_timestamp_str)) {
            $expiry_datetime = new DateTime($expiry_timestamp_str);
            $now_datetime = new DateTime();
            if ($two_fa_code === $stored_2fa_code && $now_datetime < $expiry_datetime) {
                $is_2fa_code_valid = true;
            }
        }

        if (!$is_2fa_code_valid) {
            // Don't clear the code on failed attempt here, let request_2fa_code handle new code generation
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired 2FA code.']);
            exit;
        }
    }

    // All checks passed, proceed to change password
    $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);
    if ($new_password_hash === false) {
        throw new Exception('Failed to hash new password.');
    }

    $sql_update_pass = "UPDATE Users
                        SET PasswordHash = :new_password_hash,
                            TwoFactorEmailCode = NULL,      -- Clear 2FA code after successful use
                            TwoFactorCodeExpiry = NULL
                        WHERE UserID = :user_id";
    $stmt_update_pass = $pdo->prepare($sql_update_pass);
    $stmt_update_pass->bindParam(':new_password_hash', $new_password_hash, PDO::PARAM_STR);
    $stmt_update_pass->bindParam(':user_id', $loggedInUserId, PDO::PARAM_INT);

    if ($stmt_update_pass->execute()) {
        http_response_code(200);
        echo json_encode(['message' => 'Password changed successfully.']);
    } else {
        throw new Exception('Failed to update password in the database.');
    }

} catch (Exception $e) {
    error_log("Change Password API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log("Change Password API Throwable: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected server error occurred while changing password.']);
}
exit;
?>
    
<?php
/**
 * API Endpoint: Check Session
 * Checks if a user has an active session and returns user details if logged in.
 * Uses GET method.
 */

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep errors hidden from user output
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log'); // Optional: Specify log file

// IMPORTANT: Session must be started BEFORE any output or accessing $_SESSION
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
header('Access-Control-Allow-Methods: GET, OPTIONS'); // Use GET for checking status
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true'); // Important for sessions

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Check Session Logic ---
try {
    // Check if the user_id is set in the session
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        // User is logged in, return their details
        http_response_code(200);
        echo json_encode([
            'logged_in' => true,
            'user' => [
                'user_id'     => $_SESSION['user_id'],
                'employee_id' => $_SESSION['employee_id'] ?? null,
                'username'    => $_SESSION['username'] ?? null,
                'full_name'   => $_SESSION['full_name'] ?? null,
                'role_id'     => $_SESSION['role_id'] ?? null,
                'role_name'   => $_SESSION['role_name'] ?? null,
                'hmo_enrollment' => $_SESSION['hmo_enrollment'] ?? null
            ]
        ]);
    } else {
        // User is not logged in
        http_response_code(200); // Still a successful check, just indicates not logged in
        echo json_encode(['logged_in' => false]);
    }
    exit;

} catch (Throwable $e) {
    // Catch any unexpected errors during session check
    error_log("Check Session API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred while checking session status.', 'logged_in' => false]);
    exit;
}

?>

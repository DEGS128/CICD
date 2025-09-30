<?php
/**
 * API Endpoint: Logout
 * Destroys the current user session.
 */

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep errors hidden from user output
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log'); // Optional: Specify log file

// IMPORTANT: Session must be started BEFORE any output or session manipulation
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Use POST for logout action
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true'); // Important for sessions

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check if it's a POST request (recommended for actions that change state)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'POST method required for logout.']);
    exit;
}

// --- Logout Logic ---
try {
    // 1. Unset all session variables
    $_SESSION = array();

    // 2. Delete the session cookie (optional but good practice)
    // This requires knowing the session cookie parameters used (name, path, domain)
    // If using default settings:
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, // Set expiry in the past
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // 3. Destroy the session
    session_destroy();

    // Success response with redirect
    http_response_code(200);
    echo json_encode([
        'message' => 'Logout successful.',
        'redirect_url' => '../index.php'
    ]);

    // Also set a direct redirect header as fallback
    header('Location: ../index.php');
    exit;

} catch (Throwable $e) {
    // Catch any unexpected errors during logout
    error_log("Logout API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred during logout.']);
    exit;
}

?>

<?php
/**
 *  Session Authentication Check
 * Starts session and validates user authentication.
 * Redirects to login if no valid session.
 */

// Start session
session_start();

// Check if user is authenticated
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    // Destroy any invalid session
    session_unset();
    session_destroy();
    // Redirect to login page
    header("Location: login.php");
    exit();
}

// Optional: Regenerate session ID for security (anti-session fixation)
session_regenerate_id(true);

// Log successful session validation (optional, for auditing)
error_log("Session validated for user_id: " . $_SESSION['user_id']);
?>

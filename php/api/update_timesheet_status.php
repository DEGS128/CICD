<?php
// --- Error Reporting for Debugging ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep errors hidden from user
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log'); // Optional custom log file

// --- Set Headers EARLY ---
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // IMPORTANT: Restrict this in production!
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request (sent by browsers for CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
// --- End Headers ---

// --- Database Connection ---
try {
    require_once '../db_connect.php'; // Adjust path if necessary
} catch (Throwable $e) {
    error_log("Failed to include db_connect.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: Cannot connect to database.']);
    exit;
}
// --- End Database Connection ---

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

// Extract and sanitize data
$timesheet_id = isset($input_data['timesheet_id']) ? filter_var($input_data['timesheet_id'], FILTER_VALIDATE_INT) : null;
$new_status = isset($input_data['status']) ? trim(htmlspecialchars($input_data['status'])) : null;

// --- Validate Input ---
$errors = [];
if (empty($timesheet_id) || $timesheet_id === false || $timesheet_id <= 0) {
    $errors['timesheet_id'] = 'Valid Timesheet ID is required.';
}
// Validate the status to ensure it's one of the allowed values
$allowed_statuses = ['Approved', 'Rejected']; // Define acceptable statuses for this action
if (empty($new_status) || !in_array($new_status, $allowed_statuses)) {
    $errors['status'] = 'Invalid status provided. Must be "Approved" or "Rejected".';
}

if (!empty($errors)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}
// --- End Validation ---

// --- Authorization Check (Placeholder) ---
// IMPORTANT: Implement real authentication/authorization here!
// You need to check if the currently logged-in user has permission
// to approve/reject timesheets (e.g., are they a manager or HR admin?).
$current_user_id = 1; // ** TEMPORARY Placeholder - Replace with actual logged-in user ID **
$can_approve_reject = true; // ** TEMPORARY Placeholder - Replace with actual permission check **

if (!$can_approve_reject) {
     http_response_code(403); // Forbidden
     echo json_encode(['error' => 'You do not have permission to perform this action.']);
     exit;
}
// --- End Authorization Check ---


// --- Update Database ---
try {
    $pdo->beginTransaction(); // Start transaction

    // Check if the timesheet exists and is in 'Pending' status (optional but good practice)
    $checkSql = "SELECT Status FROM Timesheets WHERE TimesheetID = :timesheet_id";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->bindParam(':timesheet_id', $timesheet_id, PDO::PARAM_INT);
    $checkStmt->execute();
    $current_timesheet = $checkStmt->fetch();

    if (!$current_timesheet) {
        $pdo->rollBack();
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Timesheet not found.']);
        exit;
    }

    // Optional: Only allow update if status is currently 'Pending'
    // if ($current_timesheet['Status'] !== 'Pending') {
    //     $pdo->rollBack();
    //     http_response_code(409); // Conflict
    //     echo json_encode(['error' => 'Timesheet is not in Pending status and cannot be updated.']);
    //     exit;
    // }


    // Prepare the update statement
    $sql = "UPDATE Timesheets
            SET Status = :new_status,
                ApprovedBy = :approver_id,
                ApprovalDate = NOW() -- Set approval date to current time
            WHERE TimesheetID = :timesheet_id";

    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':new_status', $new_status, PDO::PARAM_STR);
    $stmt->bindParam(':approver_id', $current_user_id, PDO::PARAM_INT); // Use the actual logged-in user ID
    $stmt->bindParam(':timesheet_id', $timesheet_id, PDO::PARAM_INT);

    $success = $stmt->execute();

    if ($success && $stmt->rowCount() > 0) {
        $pdo->commit(); // Commit transaction
        // Success response
        http_response_code(200); // OK
        echo json_encode([
            'message' => "Timesheet ID {$timesheet_id} has been {$new_status}.",
            'timesheet_id' => $timesheet_id,
            'new_status' => $new_status
        ]);
    } elseif ($success && $stmt->rowCount() === 0) {
         $pdo->rollBack(); // Rollback if no rows were affected (e.g., timesheet ID didn't exist)
         http_response_code(404); // Not Found
         echo json_encode(['error' => 'Timesheet not found or no changes made.']);
    }
    else {
        $pdo->rollBack(); // Rollback on execution failure
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update timesheet status in database.']);
    }

} catch (\PDOException $e) {
    $pdo->rollBack(); // Rollback on any PDO error
    error_log("API Error (update_timesheet_status - DB): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while updating timesheet status.']);
} catch (Throwable $e) { // Catch other potential errors
     if ($pdo->inTransaction()) { // Check if transaction was started before rolling back
        $pdo->rollBack();
     }
     error_log("API Error (update_timesheet_status - General): " . $e->getMessage());
     http_response_code(500);
     echo json_encode(['error' => 'An unexpected error occurred.']);
}
// --- End Update Database ---
?>

<?php
// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep errors hidden from user
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log'); // Optional custom log file

// IMPORTANT: Session must be started BEFORE any output
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // IMPORTANT: Restrict this in production!
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true'); // Needed for sessions

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
// --- End Headers ---

// --- Database Connection ---
$pdo = null;
try {
    require_once '../db_connect.php'; // Adjust path if necessary
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('DB connection failed');
    }
} catch (Throwable $e) {
    error_log("PHP Error in update_leave_request_status.php (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Server configuration error.']);
    exit;
}
// --- End Database Connection ---

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'POST method required.']);
    exit;
}

// --- Authentication & Authorization Check ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['employee_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Authentication required. Please log in.']);
    exit;
}
// Define roles allowed to perform this action (e.g., System Admin=1, HR Admin=2, Manager=4)
$allowed_roles = [1, 2, 4];
if (!isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], $allowed_roles)) {
     http_response_code(403); // Forbidden
     echo json_encode(['error' => 'Permission denied. You do not have rights to approve/reject leave requests.']);
     exit;
}
$approver_employee_id = $_SESSION['employee_id']; // EmployeeID of the logged-in approver
$approver_user_id = $_SESSION['user_id'];     // UserID of the logged-in approver
// --- End Auth Check ---


// --- Get Data from POST Request (expecting JSON) ---
$input_data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload received.']);
    exit;
}

// --- Extract and sanitize data ---
$request_id = isset($input_data['request_id']) ? filter_var($input_data['request_id'], FILTER_VALIDATE_INT) : null;
$new_status = isset($input_data['new_status']) ? trim(htmlspecialchars($input_data['new_status'])) : null;
// Approver ID from input is now taken from session, but we still get comments
$comments = isset($input_data['comments']) ? trim(htmlspecialchars($input_data['comments'])) : null;

// --- Validate Input ---
$errors = [];
if (empty($request_id) || $request_id <= 0) {
    $errors['request_id'] = 'Valid Leave Request ID is required.';
}
$allowed_statuses = ['Approved', 'Rejected']; // Only allow these statuses via this endpoint
if (empty($new_status) || !in_array($new_status, $allowed_statuses)) {
    $errors['new_status'] = 'Invalid status provided. Must be "Approved" or "Rejected".';
}
// Approver ID is now taken from session, so no need to validate it from input here.
if (empty($comments)) $comments = null; // Allow empty comments

if (!empty($errors)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}
// --- End Validation ---


// --- Update Database ---
try {
    $pdo->beginTransaction(); // Start transaction

    // 1. Check current status and get EmployeeID of the requester
    $sql_get_request = "SELECT EmployeeID, Status FROM LeaveRequests WHERE RequestID = :request_id";
    $stmt_get_request = $pdo->prepare($sql_get_request);
    $stmt_get_request->bindParam(':request_id', $request_id, PDO::PARAM_INT);
    $stmt_get_request->execute();
    $current_request = $stmt_get_request->fetch(PDO::FETCH_ASSOC);

    if (!$current_request) {
        $pdo->rollBack();
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Leave Request not found.']);
        exit;
    }
    $requester_employee_id = $current_request['EmployeeID'];

    // Only allow update if status is currently 'Pending'
    if ($current_request['Status'] !== 'Pending') {
        $pdo->rollBack();
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'Leave Request is not in Pending status and cannot be updated.']);
        exit;
    }

    // 2. Update the LeaveRequests table
    $sql_update = "UPDATE LeaveRequests
                   SET Status = :new_status,
                       ApproverID = :approver_employee_id, -- Use EmployeeID of approver
                       ApprovalDate = NOW(),
                       ApproverComments = :comments
                   WHERE RequestID = :request_id";

    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->bindParam(':new_status', $new_status, PDO::PARAM_STR);
    $stmt_update->bindParam(':approver_employee_id', $approver_employee_id, PDO::PARAM_INT);
    $stmt_update->bindParam(':comments', $comments, PDO::PARAM_STR);
    $stmt_update->bindParam(':request_id', $request_id, PDO::PARAM_INT);
    $success = $stmt_update->execute();

    if (!$success || $stmt_update->rowCount() === 0) {
        $pdo->rollBack();
        http_response_code(500); // Or 404 if rowCount is 0 and it implies not found
        echo json_encode(['error' => 'Failed to update leave request status in the database.']);
        exit;
    }

    // 3. Update LeaveBalances if approved (CRITICAL: Needs careful logic)
    // This part is complex and depends on your specific leave balance calculation rules.
    // For now, we'll skip the direct balance update here and assume it might be handled
    // by a separate process or needs more detailed implementation based on accrual rules, year, etc.
    // error_log("Leave Request {$request_id} for Employee {$requester_employee_id} was {$new_status}. Balance update logic to be implemented if needed.");


    // --- START: Notification Logic for Employee ---
    if ($requester_employee_id) {
        // Get the UserID of the employee who submitted the leave request
        $sql_get_requester_user = "SELECT UserID FROM Users WHERE EmployeeID = :employee_id";
        $stmt_get_requester_user = $pdo->prepare($sql_get_requester_user);
        $stmt_get_requester_user->bindParam(':employee_id', $requester_employee_id, PDO::PARAM_INT);
        $stmt_get_requester_user->execute();
        $requester_user = $stmt_get_requester_user->fetch(PDO::FETCH_ASSOC);

        if ($requester_user && isset($requester_user['UserID'])) {
            $recipient_user_id = $requester_user['UserID'];

            $notificationMessage = "Your leave request (#{$request_id}) has been {$new_status}.";
            if ($new_status === 'Rejected' && !empty($comments)) {
                $notificationMessage .= " Reason: " . $comments;
            } elseif ($new_status === 'Approved' && !empty($comments)) {
                 $notificationMessage .= " Comments: " . $comments;
            }
            $notificationLink = "#leave-requests"; // Link to their "My Leave Requests" page
            $notificationType = "LEAVE_" . strtoupper($new_status); // e.g., LEAVE_APPROVED

            $sql_notify = "INSERT INTO Notifications (UserID, SenderUserID, NotificationType, Message, Link, IsRead, CreatedAt)
                           VALUES (:recipient_user_id, :sender_user_id, :type, :message, :link, FALSE, NOW())";
            $stmt_notify = $pdo->prepare($sql_notify);
            $stmt_notify->execute([
                ':recipient_user_id' => $recipient_user_id,
                ':sender_user_id' => $approver_user_id, // UserID of the admin/manager who actioned it
                ':type' => $notificationType,
                ':message' => $notificationMessage,
                ':link' => $notificationLink
            ]);
            error_log("Notification created for UserID {$recipient_user_id} regarding Leave Request {$request_id}.");
        } else {
            error_log("Could not find UserID for EmployeeID {$requester_employee_id} to send leave status notification.");
        }
    }
    // --- END: Notification Logic for Employee ---


    // Commit transaction if everything succeeded
    $pdo->commit();
    http_response_code(200); // OK
    echo json_encode([
        'message' => "Leave Request ID {$request_id} has been {$new_status}.",
        'request_id' => $request_id,
        'new_status' => $new_status
    ]);

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("PHP PDOException in update_leave_request_status.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while updating leave request status.']);
} catch (Throwable $e) { // Catch other potential errors
     if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
     error_log("PHP Throwable in update_leave_request_status.php: " . $e->getMessage());
     http_response_code(500);
     echo json_encode(['error' => 'An unexpected server error occurred.']);
}
// --- End Update Database ---
exit;
?>

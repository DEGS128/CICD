<?php
/**
 * API Endpoint: Update Claim Status
 * Allows authorized users to approve or reject a claim. Records the action.
 * Notifies the employee about the status change.
 * v2.1 - Added employee notification.
 */

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log');

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
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
         throw new Exception('$pdo object not created by db_connect.php');
    }
} catch (Throwable $e) {
    error_log("CRITICAL PHP Error in update_claim_status.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: Cannot connect to database.']);
    exit;
}

// --- Authentication & Authorization Check ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['employee_id'])) { // Check employee_id as well for approver
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required. Please log in.']);
    exit;
}
$allowed_roles = [1, 2, 4]; // System Admin, HR Admin, Manager
if (!isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], $allowed_roles)) {
     http_response_code(403);
     echo json_encode(['error' => 'Permission denied. You do not have rights to approve/reject claims.']);
     exit;
}
$approver_employee_id = $_SESSION['employee_id']; // EmployeeID of the logged-in approver
$approver_user_id = $_SESSION['user_id']; // UserID of the logged-in approver
// --- End Auth Check ---

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST method required.']);
    exit;
}

$input_data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload. Error: ' . json_last_error_msg()]);
    exit;
}

$claim_id = isset($input_data['claim_id']) ? filter_var($input_data['claim_id'], FILTER_VALIDATE_INT) : null;
$new_status = isset($input_data['new_status']) ? trim(htmlspecialchars($input_data['new_status'])) : null;
$comments = isset($input_data['comments']) ? trim(htmlspecialchars($input_data['comments'])) : null;

$errors = [];
if (empty($claim_id) || $claim_id <= 0) $errors['claim_id'] = 'Valid Claim ID is required.';
$allowed_statuses = ['Approved', 'Rejected', 'Queried', 'Paid'];
if (empty($new_status) || !in_array($new_status, $allowed_statuses)) {
    $errors['new_status'] = 'Invalid status. Must be one of: ' . implode(', ', $allowed_statuses);
}
if (empty($comments)) $comments = null;

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get Claim Submitter's EmployeeID and UserID
    $stmt_claim_info = $pdo->prepare("SELECT c.EmployeeID, u.UserID AS SubmitterUserID
                                      FROM Claims c
                                      JOIN Users u ON c.EmployeeID = u.EmployeeID
                                      WHERE c.ClaimID = :claim_id");
    $stmt_claim_info->bindParam(':claim_id', $claim_id, PDO::PARAM_INT);
    $stmt_claim_info->execute();
    $claim_info = $stmt_claim_info->fetch(PDO::FETCH_ASSOC);

    if (!$claim_info) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Claim not found.']);
        exit;
    }
    $claim_submitter_employee_id = $claim_info['EmployeeID'];
    $claim_submitter_user_id = $claim_info['SubmitterUserID'];


    $sql_update_claim = "UPDATE Claims SET Status = :new_status WHERE ClaimID = :claim_id";
    $stmt_update_claim = $pdo->prepare($sql_update_claim);
    $stmt_update_claim->bindParam(':new_status', $new_status, PDO::PARAM_STR);
    $stmt_update_claim->bindParam(':claim_id', $claim_id, PDO::PARAM_INT);
    $stmt_update_claim->execute();

    if ($stmt_update_claim->rowCount() === 0) {
         $pdo->rollBack();
         http_response_code(404);
         echo json_encode(['error' => 'Claim not found or status could not be updated.']);
         exit;
    }

    $sql_insert_approval = "INSERT INTO ClaimApprovals (ClaimID, ApproverID, ApprovalDate, Status, Comments)
                            VALUES (:claim_id, :approver_employee_id, NOW(), :status, :comments)";
    $stmt_insert_approval = $pdo->prepare($sql_insert_approval);
    $stmt_insert_approval->bindParam(':claim_id', $claim_id, PDO::PARAM_INT);
    $stmt_insert_approval->bindParam(':approver_employee_id', $approver_employee_id, PDO::PARAM_INT);
    $stmt_insert_approval->bindParam(':status', $new_status, PDO::PARAM_STR);
    $stmt_insert_approval->bindParam(':comments', $comments, PDO::PARAM_STR);
    $stmt_insert_approval->execute();

    // --- Create Notification for the Employee who submitted the claim ---
    if ($claim_submitter_user_id) {
        $notificationMessage = "Your claim (#{$claim_id}) has been {$new_status}.";
        if ($new_status === 'Rejected' && $comments) {
            $notificationMessage .= " Reason: " . $comments;
        } elseif ($new_status === 'Approved' && $comments) {
             $notificationMessage .= " Comments: " . $comments;
        }
        $notificationLink = "#my-claims"; // Link to their "My Claims" page
        $notificationType = "CLAIM_" . strtoupper($new_status); // e.g., CLAIM_APPROVED

        $sql_notify = "INSERT INTO Notifications (UserID, SenderUserID, NotificationType, Message, Link, IsRead, CreatedAt)
                       VALUES (:recipient_user_id, :sender_user_id, :type, :message, :link, FALSE, NOW())";
        $stmt_notify = $pdo->prepare($sql_notify);
        $stmt_notify->execute([
            ':recipient_user_id' => $claim_submitter_user_id,
            ':sender_user_id' => $approver_user_id, // The admin/manager who actioned it
            ':type' => $notificationType,
            ':message' => $notificationMessage,
            ':link' => $notificationLink
        ]);
    }
    // --- End Notification Creation ---

    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        'message' => "Claim ID {$claim_id} status updated to {$new_status}.",
        'claim_id' => $claim_id,
        'new_status' => $new_status
    ]);

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("PHP PDOException in update_claim_status.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while updating claim status.']);

} catch (Throwable $e) {
     if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
     error_log("PHP Throwable in update_claim_status.php: " . $e->getMessage());
     http_response_code(500);
     echo json_encode(['error' => 'An unexpected server error occurred.']);
}
exit;
?>

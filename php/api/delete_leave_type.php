<?php
    // --- Error Reporting & Headers ---
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS, DELETE'); // Allow POST/DELETE
    header('Access-Control-Allow-Headers: Content-Type');
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

    // --- Database Connection ---
    $pdo = null;
    try {
        require_once '../db_connect.php';
         if (!isset($pdo) || !$pdo instanceof PDO) { throw new Exception('DB connection failed'); }
    } catch (Throwable $e) {
        error_log("PHP Error in delete_leave_type.php (db_connect include): " . $e->getMessage());
        if (!headers_sent()) { http_response_code(500); }
        echo json_encode(['error' => 'Server configuration error.']); exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { // Using POST from JS
        if (!headers_sent()) { http_response_code(405); }
        echo json_encode(['error' => 'POST method required for delete.']); exit;
    }

    // --- Get Data ---
    $input_data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        if (!headers_sent()) { http_response_code(400); }
        echo json_encode(['error' => 'Invalid JSON payload.']); exit;
    }

    // --- Extract & Validate ID ---
    $leave_type_id = isset($input_data['leave_type_id']) ? filter_var($input_data['leave_type_id'], FILTER_VALIDATE_INT) : null;
    if (empty($leave_type_id) || $leave_type_id <= 0) {
        if (!headers_sent()) { http_response_code(400); }
        echo json_encode(['error' => 'Valid Leave Type ID is required.']); exit;
    }
    // --- End Validation ---

    // --- Authorization (Placeholder) ---
    $can_manage = true; // Replace with real check
    if (!$can_manage) {
        if (!headers_sent()) { http_response_code(403); }
        echo json_encode(['error' => 'Permission denied.']); exit;
    }
    // --- End Authorization ---

    // --- Delete ---
    try {
        // Check if leave type is in use (LeaveRequests or LeaveBalances)
        $checkSql1 = "SELECT COUNT(*) FROM LeaveRequests WHERE LeaveTypeID = :id";
        $checkStmt1 = $pdo->prepare($checkSql1);
        $checkStmt1->bindParam(':id', $leave_type_id, PDO::PARAM_INT);
        $checkStmt1->execute();
        $usageCount1 = $checkStmt1->fetchColumn();

        $checkSql2 = "SELECT COUNT(*) FROM LeaveBalances WHERE LeaveTypeID = :id";
        $checkStmt2 = $pdo->prepare($checkSql2);
        $checkStmt2->bindParam(':id', $leave_type_id, PDO::PARAM_INT);
        $checkStmt2->execute();
        $usageCount2 = $checkStmt2->fetchColumn();

        if ($usageCount1 > 0 || $usageCount2 > 0) {
             if (!headers_sent()) { http_response_code(409); } // Conflict
             echo json_encode(['error' => 'Cannot delete leave type because it is currently in use in requests or balances.']);
             exit;
        }

        // Proceed with deletion if not in use
        $sql = "DELETE FROM LeaveTypes WHERE LeaveTypeID = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $leave_type_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            if (!headers_sent()) { http_response_code(200); }
            echo json_encode(['message' => 'Leave type deleted successfully.']);
        } else {
            if (!headers_sent()) { http_response_code(404); }
            echo json_encode(['error' => 'Leave type not found with the specified ID.']);
        }

    } catch (\PDOException $e) {
        error_log("PHP PDOException in delete_leave_type.php: " . $e->getMessage());
        if (!headers_sent()) { http_response_code($e->getCode() == '23000' ? 409 : 500); }
        echo json_encode(['error' => $e->getCode() == '23000' ? 'Cannot delete leave type, constraint violation.' : 'Database error deleting leave type.']);
    } catch (Throwable $e) {
        error_log("PHP Throwable in delete_leave_type.php: " . $e->getMessage());
        if (!headers_sent()) { http_response_code(500); }
        echo json_encode(['error' => 'Unexpected server error deleting leave type.']);
    }
    exit;
    ?>
    
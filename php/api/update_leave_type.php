<?php
    // --- Error Reporting & Headers ---
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS'); // Using POST for update
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
        error_log("PHP Error in update_leave_type.php (db_connect include): " . $e->getMessage());
        if (!headers_sent()) { http_response_code(500); }
        echo json_encode(['error' => 'Server configuration error.']); exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        if (!headers_sent()) { http_response_code(405); }
        echo json_encode(['error' => 'POST method required for update.']); exit;
    }

    // --- Get Data ---
    $input_data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        if (!headers_sent()) { http_response_code(400); }
        echo json_encode(['error' => 'Invalid JSON payload.']); exit;
    }

    // --- Extract & Sanitize ---
    $leave_type_id = isset($input_data['leave_type_id']) ? filter_var($input_data['leave_type_id'], FILTER_VALIDATE_INT) : null;
    $type_name = isset($input_data['type_name']) ? trim(htmlspecialchars($input_data['type_name'])) : null;
    $description = isset($input_data['description']) ? trim(htmlspecialchars($input_data['description'])) : null;
    $accrual_rate = isset($input_data['accrual_rate']) && ($input_data['accrual_rate'] === '' || is_numeric($input_data['accrual_rate'])) ? ($input_data['accrual_rate'] === '' ? null : (float)$input_data['accrual_rate']) : false; // Allow empty string to mean NULL, check numeric otherwise
    $max_carry = isset($input_data['max_carry_forward_days']) && ($input_data['max_carry_forward_days'] === '' || is_numeric($input_data['max_carry_forward_days'])) ? ($input_data['max_carry_forward_days'] === '' ? null : (float)$input_data['max_carry_forward_days']) : false; // Allow empty string to mean NULL
    $requires_approval_input = isset($input_data['requires_approval']) ? filter_var($input_data['requires_approval'], FILTER_VALIDATE_INT) : null;

    // --- Validate ---
    $errors = [];
    if (empty($leave_type_id) || $leave_type_id <= 0) $errors['leave_type_id'] = 'Valid Leave Type ID is required for update.';
    if (empty($type_name)) $errors['type_name'] = 'Leave Type Name is required.';
    if ($accrual_rate === false || ($accrual_rate !== null && $accrual_rate < 0)) $errors['accrual_rate'] = 'Accrual Rate must be empty or a non-negative number.';
    if ($max_carry === false || ($max_carry !== null && $max_carry < 0)) $errors['max_carry_forward_days'] = 'Max Carry Forward must be empty or a non-negative number.';
    if ($requires_approval_input === null || ($requires_approval_input !== 0 && $requires_approval_input !== 1)) {
         $errors['requires_approval'] = 'Requires Approval must be 0 or 1.';
         $requires_approval_db = 1; // Default
    } else { $requires_approval_db = $requires_approval_input; }
    if (empty($description)) $description = null;

    if (!empty($errors)) {
        if (!headers_sent()) { http_response_code(400); }
        echo json_encode(['error' => 'Validation failed.', 'details' => $errors]); exit;
    }
    // --- End Validation ---

    // --- Authorization (Placeholder) ---
    $can_manage = true; // Replace with real check
    if (!$can_manage) {
        if (!headers_sent()) { http_response_code(403); }
        echo json_encode(['error' => 'Permission denied.']); exit;
    }
    // --- End Authorization ---

    // --- Update ---
    try {
        $sql = "UPDATE LeaveTypes
                SET TypeName = :type_name,
                    Description = :description,
                    RequiresApproval = :requires_approval,
                    AccrualRate = :accrual_rate,
                    MaxCarryForwardDays = :max_carry
                WHERE LeaveTypeID = :leave_type_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':type_name', $type_name, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':requires_approval', $requires_approval_db, PDO::PARAM_INT);
        $stmt->bindParam(':accrual_rate', $accrual_rate);
        $stmt->bindParam(':max_carry', $max_carry);
        $stmt->bindParam(':leave_type_id', $leave_type_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
             if (!headers_sent()) { http_response_code(200); }
             echo json_encode(['message' => 'Leave type updated successfully.', 'leave_type_id' => $leave_type_id]);
        } else {
            // Check if ID existed but nothing changed, or if ID didn't exist
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM LeaveTypes WHERE LeaveTypeID = :id");
            $checkStmt->bindParam(':id', $leave_type_id, PDO::PARAM_INT);
            $checkStmt->execute();
            if ($checkStmt->fetchColumn() > 0) {
                 if (!headers_sent()) { http_response_code(200); }
                 echo json_encode(['message' => 'Leave type details submitted, no changes detected.', 'leave_type_id' => $leave_type_id]);
            } else {
                 if (!headers_sent()) { http_response_code(404); }
                 echo json_encode(['error' => 'Leave type not found with the specified ID.']);
            }
        }

    } catch (\PDOException $e) {
        error_log("PHP PDOException in update_leave_type.php: " . $e->getMessage());
        if (!headers_sent()) { http_response_code($e->getCode() == '23000' ? 409 : 500); }
        echo json_encode(['error' => $e->getCode() == '23000' ? 'Leave type name conflict.' : 'Database error updating leave type.']);
    } catch (Throwable $e) {
        error_log("PHP Throwable in update_leave_type.php: " . $e->getMessage());
        if (!headers_sent()) { http_response_code(500); }
        echo json_encode(['error' => 'Unexpected server error updating leave type.']);
    }
    exit;
    ?>
    
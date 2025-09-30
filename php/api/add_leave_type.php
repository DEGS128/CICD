<?php
    // --- Error Reporting & Headers ---
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
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
        error_log("PHP Error in add_leave_type.php (db_connect include): " . $e->getMessage());
        if (!headers_sent()) { http_response_code(500); }
        echo json_encode(['error' => 'Server configuration error.']); exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        if (!headers_sent()) { http_response_code(405); }
        echo json_encode(['error' => 'POST method required.']); exit;
    }

    // --- Get Data ---
    $input_data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        if (!headers_sent()) { http_response_code(400); }
        echo json_encode(['error' => 'Invalid JSON payload.']); exit;
    }

    // --- Extract & Sanitize ---
    $type_name = isset($input_data['type_name']) ? trim(htmlspecialchars($input_data['type_name'])) : null;
    $description = isset($input_data['description']) ? trim(htmlspecialchars($input_data['description'])) : null;
    $accrual_rate = isset($input_data['accrual_rate']) && is_numeric($input_data['accrual_rate']) ? (float)$input_data['accrual_rate'] : null;
    $max_carry = isset($input_data['max_carry_forward_days']) && is_numeric($input_data['max_carry_forward_days']) ? (float)$input_data['max_carry_forward_days'] : null;
    $requires_approval_input = isset($input_data['requires_approval']) ? filter_var($input_data['requires_approval'], FILTER_VALIDATE_INT) : null;

    // --- Validate ---
    $errors = [];
    if (empty($type_name)) $errors['type_name'] = 'Leave Type Name is required.';
    if ($accrual_rate !== null && $accrual_rate < 0) $errors['accrual_rate'] = 'Accrual Rate cannot be negative.';
    if ($max_carry !== null && $max_carry < 0) $errors['max_carry_forward_days'] = 'Max Carry Forward cannot be negative.';
    if ($requires_approval_input === null || ($requires_approval_input !== 0 && $requires_approval_input !== 1)) {
         $errors['requires_approval'] = 'Requires Approval must be 0 or 1.';
         $requires_approval_db = 1; // Default to true if invalid
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

    // --- Insert ---
    try {
        $sql = "INSERT INTO LeaveTypes (TypeName, Description, RequiresApproval, AccrualRate, MaxCarryForwardDays)
                VALUES (:type_name, :description, :requires_approval, :accrual_rate, :max_carry)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':type_name', $type_name, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':requires_approval', $requires_approval_db, PDO::PARAM_INT);
        $stmt->bindParam(':accrual_rate', $accrual_rate); // PDO handles float/null
        $stmt->bindParam(':max_carry', $max_carry);     // PDO handles float/null
        $stmt->execute();
        $new_id = $pdo->lastInsertId();

        if (!headers_sent()) { http_response_code(201); }
        echo json_encode(['message' => 'Leave type added successfully.', 'leave_type_id' => $new_id]);

    } catch (\PDOException $e) {
        error_log("PHP PDOException in add_leave_type.php: " . $e->getMessage());
        if (!headers_sent()) { http_response_code($e->getCode() == '23000' ? 409 : 500); } // 409 for duplicate
        echo json_encode(['error' => $e->getCode() == '23000' ? 'Leave type name already exists.' : 'Database error adding leave type.']);
    } catch (Throwable $e) {
        error_log("PHP Throwable in add_leave_type.php: " . $e->getMessage());
        if (!headers_sent()) { http_response_code(500); }
        echo json_encode(['error' => 'Unexpected server error adding leave type.']);
    }
    exit;
    ?>
    
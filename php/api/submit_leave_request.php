<?php
    // --- Error Reporting & Headers ---
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    // ini_set('error_log', '/path/to/your/php-error.log');

    session_start(); // Required for SenderUserID

    // Check if user is authenticated
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required. Please log in.']);
        exit();
    }

    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Credentials: true');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

    // --- Database Connection ---
    $pdo = null;
    try {
        require_once '../db_connect.php';
        if (!isset($pdo) || !$pdo instanceof PDO) { throw new Exception('DB connection failed'); }
    } catch (Throwable $e) {
        error_log("PHP Error in submit_leave_request.php (db_connect include): " . $e->getMessage());
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
    $employee_id = isset($input_data['employee_id']) ? filter_var($input_data['employee_id'], FILTER_VALIDATE_INT) : null;
    $leave_type_id = isset($input_data['leave_type_id']) ? filter_var($input_data['leave_type_id'], FILTER_VALIDATE_INT) : null;
    $start_date = isset($input_data['start_date']) ? $input_data['start_date'] : null;
    $end_date = isset($input_data['end_date']) ? $input_data['end_date'] : null;
    $num_days = isset($input_data['number_of_days']) ? filter_var($input_data['number_of_days'], FILTER_VALIDATE_FLOAT) : null;
    $reason = isset($input_data['reason']) ? trim(htmlspecialchars($input_data['reason'])) : null;
    $initial_status = 'Pending';
    // --- End Extract & Sanitize ---

    // --- Validate ---
    $errors = [];
    if (empty($employee_id) || $employee_id <= 0) $errors['employee_id'] = 'Valid Employee ID is required.';
    if (empty($leave_type_id) || $leave_type_id <= 0) $errors['leave_type_id'] = 'Valid Leave Type is required.';
    if (empty($start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) $errors['start_date'] = 'Valid Start Date (YYYY-MM-DD) is required.';
    if (empty($end_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        $errors['end_date'] = 'Valid End Date (YYYY-MM-DD) is required.';
    } elseif ($end_date < $start_date) {
        $errors['end_date'] = 'End Date cannot be before Start Date.';
    }
    if ($num_days === null || $num_days <= 0) $errors['number_of_days'] = 'Valid Number of Days (positive) is required.';
    if (empty($reason)) $reason = null;

    if (!empty($errors)) {
        if (!headers_sent()) { http_response_code(400); }
        echo json_encode(['error' => 'Validation failed.', 'details' => $errors]); exit;
    }
    // --- End Validation ---

    // --- Insert ---
    try {
        $pdo->beginTransaction();

        $sql = "INSERT INTO LeaveRequests (EmployeeID, LeaveTypeID, StartDate, EndDate, NumberOfDays, Reason, Status, RequestDate)
                VALUES (:employee_id, :leave_type_id, :start_date, :end_date, :num_days, :reason, :status, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
        $stmt->bindParam(':leave_type_id', $leave_type_id, PDO::PARAM_INT);
        $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
        $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
        $stmt->bindParam(':num_days', $num_days);
        $stmt->bindParam(':reason', $reason, PDO::PARAM_STR);
        $stmt->bindParam(':status', $initial_status, PDO::PARAM_STR);
        $stmt->execute();
        $new_request_id = $pdo->lastInsertId();

        // --- Create Notification for System Admins ---
        if ($new_request_id) {
            $adminUserIds = [];
            $stmt_admins = $pdo->query("SELECT UserID FROM Users WHERE RoleID = 1 AND IsActive = TRUE"); // RoleID 1 for System Admin
            while ($admin_row = $stmt_admins->fetch(PDO::FETCH_ASSOC)) {
                $adminUserIds[] = $admin_row['UserID'];
            }

            if (!empty($adminUserIds)) {
                $employeeName = "Employee ID " . $employee_id;
                $stmt_emp_name = $pdo->prepare("SELECT CONCAT(FirstName, ' ', LastName) AS FullName FROM Employees WHERE EmployeeID = :emp_id");
                $stmt_emp_name->bindParam(':emp_id', $employee_id, PDO::PARAM_INT);
                $stmt_emp_name->execute();
                $emp_name_row = $stmt_emp_name->fetch(PDO::FETCH_ASSOC);
                if ($emp_name_row) {
                    $employeeName = $emp_name_row['FullName'];
                }

                $notificationMessage = "New leave request (#{$new_request_id}) submitted by {$employeeName} for {$num_days} day(s).";
                $notificationLink = "#leave-requests"; // Link to the leave requests approval page
                $notificationType = "NEW_LEAVE_REQUEST";
                $senderUserId = null;

                $stmt_sender_user = $pdo->prepare("SELECT UserID FROM Users WHERE EmployeeID = :employee_id_sender");
                $stmt_sender_user->bindParam(':employee_id_sender', $employee_id, PDO::PARAM_INT);
                $stmt_sender_user->execute();
                $sender_user_row = $stmt_sender_user->fetch(PDO::FETCH_ASSOC);
                if ($sender_user_row) {
                    $senderUserId = $sender_user_row['UserID'];
                }

                $sql_notify = "INSERT INTO Notifications (UserID, SenderUserID, NotificationType, Message, Link, IsRead, CreatedAt)
                               VALUES (:recipient_user_id, :sender_user_id, :type, :message, :link, FALSE, NOW())";
                $stmt_notify = $pdo->prepare($sql_notify);

                foreach ($adminUserIds as $adminUserId) {
                    $stmt_notify->execute([
                        ':recipient_user_id' => $adminUserId,
                        ':sender_user_id' => $senderUserId,
                        ':type' => $notificationType,
                        ':message' => $notificationMessage,
                        ':link' => $notificationLink
                    ]);
                }
            }
        }
        // --- End Notification Creation ---

        $pdo->commit();

        if (!headers_sent()) { http_response_code(201); }
        echo json_encode(['message' => 'Leave request submitted successfully.', 'request_id' => $new_request_id]);

    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("PHP PDOException in submit_leave_request.php: " . $e->getMessage());
        if (!headers_sent()) { http_response_code($e->getCode() == '23000' ? 400 : 500); }
        echo json_encode(['error' => $e->getCode() == '23000' ? 'Invalid Employee or Leave Type ID.' : 'Database error submitting leave request.']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("PHP Throwable in submit_leave_request.php: " . $e->getMessage());
        if (!headers_sent()) { http_response_code(500); }
        echo json_encode(['error' => 'Unexpected server error submitting leave request.']);
    }
    exit;
?>

<?php
    // --- Error Reporting & Headers ---
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);

    // --- Database Connection ---
    $pdo = null;
    try {
        require_once '../db_connect.php';
        if (!isset($pdo) || !$pdo instanceof PDO) { throw new Exception('DB connection failed'); }
    } catch (Throwable $e) {
        error_log("PHP Error in get_leave_requests.php (db_connect include): " . $e->getMessage());
        if (!headers_sent()) { http_response_code(500); }
        echo json_encode(['error' => 'Server configuration error.']); exit;
    }

    // --- Filters ---
    $employee_id_filter = isset($_GET['employee_id']) ? filter_var($_GET['employee_id'], FILTER_VALIDATE_INT) : null;
    $status_filter = isset($_GET['status']) ? trim(htmlspecialchars($_GET['status'])) : null;
    // --- End Filters ---

    // --- Fetch Logic ---
    $sql = '';
    $params = [];
    try {
        $sql = "SELECT
                    lr.RequestID,
                    lr.EmployeeID,
                    CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                    lr.LeaveTypeID,
                    lt.TypeName AS LeaveTypeName,
                    lr.StartDate,
                    lr.EndDate,
                    lr.NumberOfDays,
                    lr.Reason,
                    lr.Status,
                    lr.RequestDate,
                    lr.ApproverID,
                    CONCAT(a.FirstName, ' ', a.LastName) AS ApproverName,
                    lr.ApprovalDate,
                    lr.ApproverComments
                FROM
                    leaverequests lr -- Ensure table name matches schema
                JOIN
                    Employees e ON lr.EmployeeID = e.EmployeeID
                JOIN
                    leavetypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
                LEFT JOIN -- Left join for approver in case it's NULL
                    Employees a ON lr.ApproverID = a.EmployeeID";

        $conditions = [];
        // $params initialized above

        if ($employee_id_filter !== null && $employee_id_filter > 0) {
            $conditions[] = "lr.EmployeeID = :employee_id";
            $params[':employee_id'] = $employee_id_filter;
        }
        if (!empty($status_filter)) {
            $conditions[] = "lr.Status = :status";
            $params[':status'] = $status_filter;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY lr.RequestDate DESC"; // Show most recent first

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format dates
        foreach ($requests as &$req) {
            if (!empty($req['StartDate'])) $req['StartDateFormatted'] = date('M d, Y', strtotime($req['StartDate']));
            if (!empty($req['EndDate'])) $req['EndDateFormatted'] = date('M d, Y', strtotime($req['EndDate']));
            if (!empty($req['RequestDate'])) $req['RequestDateFormatted'] = date('M d, Y H:i', strtotime($req['RequestDate']));
            if (!empty($req['ApprovalDate'])) $req['ApprovalDateFormatted'] = date('M d, Y H:i', strtotime($req['ApprovalDate']));
        }
        unset($req);

        if (headers_sent()) { exit; }
        http_response_code(200);
        echo json_encode($requests);

    } catch (\PDOException $e) {
        error_log("PHP PDOException in get_leave_requests.php: " . $e->getMessage() . " | SQL: " . $sql);
        if (!headers_sent()) { http_response_code(500); }
        echo json_encode(['error' => 'Database error retrieving leave requests.']);
    } catch (Throwable $e) {
        error_log("PHP Throwable in get_leave_requests.php: " . $e->getMessage());
        if (!headers_sent()) { http_response_code(500); }
        echo json_encode(['error' => 'Unexpected server error retrieving leave requests.']);
    }
    exit;
    ?>
    
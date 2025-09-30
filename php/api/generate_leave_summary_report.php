<?php
// hr34/php/api/generate_leave_summary_report.php

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0); 
ini_set('log_errors', 1);

session_start(); 

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
        throw new Exception('Database connection object ($pdo) not properly created by db_connect.php.');
    }
} catch (Throwable $e) {
    error_log("PHP Error in " . __FILE__ . " (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { 
        header('Content-Type: application/json'); 
        http_response_code(500); 
    }
    echo json_encode(['error' => 'Server configuration error: Could not connect to the database.']);
    exit;
}

// --- Authorization Check ---
$allowed_roles = [1, 2]; // System Admin, HR Admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || !in_array((int)$_SESSION['role_id'], $allowed_roles)) {
     http_response_code(403); 
     echo json_encode(['error' => 'Permission denied. You do not have rights to generate this report.']);
     exit;
}

// --- Report Parameters ---
// Example: ?date_range=YYYY-MM-DD_YYYY-MM-DD&department_id=X&employee_id=Y&status=Approved
$date_range_str = isset($_GET['date_range']) ? trim(htmlspecialchars($_GET['date_range'])) : null;
$department_id_filter = isset($_GET['department_id']) ? filter_var($_GET['department_id'], FILTER_VALIDATE_INT) : null;
$employee_id_filter = isset($_GET['employee_id']) ? filter_var($_GET['employee_id'], FILTER_VALIDATE_INT) : null;
$status_filter = isset($_GET['status']) ? trim(htmlspecialchars($_GET['status'])) : null; // e.g., 'Approved', 'Pending'

$report_data = [
    'reportName' => 'Leave Summary Report',
    'generatedAt' => date('Y-m-d H:i:s'),
    'columns' => [],
    'rows' => [],
    'error' => null
];

try {
    $report_data['columns'] = [
        ['key' => 'RequestID', 'label' => 'Req. ID'],
        ['key' => 'EmployeeName', 'label' => 'Employee'],
        ['key' => 'DepartmentName', 'label' => 'Department'],
        ['key' => 'LeaveTypeName', 'label' => 'Leave Type'],
        ['key' => 'StartDateFormatted', 'label' => 'Start Date'],
        ['key' => 'EndDateFormatted', 'label' => 'End Date'],
        ['key' => 'NumberOfDays', 'label' => 'Days'],
        ['key' => 'Status', 'label' => 'Status'],
        ['key' => 'RequestDateFormatted', 'label' => 'Requested On'],
        ['key' => 'ApproverName', 'label' => 'Approver'],
        ['key' => 'ApprovalDateFormatted', 'label' => 'Approved On']
    ];

    $sql = "SELECT
                lr.RequestID,
                CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                os.DepartmentName,
                lt.TypeName AS LeaveTypeName,
                lr.StartDate,
                lr.EndDate,
                lr.NumberOfDays,
                lr.Status,
                lr.RequestDate,
                CONCAT(app_e.FirstName, ' ', app_e.LastName) AS ApproverName,
                lr.ApprovalDate
            FROM leaverequests lr
            JOIN employees e ON lr.EmployeeID = e.EmployeeID
            JOIN leavetypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
            LEFT JOIN organizationalstructure os ON e.DepartmentID = os.DepartmentID
            LEFT JOIN employees app_e ON lr.ApproverID = app_e.EmployeeID";

    $conditions = [];
    $params = [];

    if ($date_range_str) {
        $dates = explode('_', $date_range_str);
        if (count($dates) == 2 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dates[0]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dates[1])) {
            $conditions[] = "(lr.StartDate BETWEEN :start_date_range AND :end_date_range OR lr.EndDate BETWEEN :start_date_range AND :end_date_range)";
            $params[':start_date_range'] = $dates[0];
            $params[':end_date_range'] = $dates[1];
        }
    }
    if ($department_id_filter) {
        $conditions[] = "e.DepartmentID = :department_id";
        $params[':department_id'] = $department_id_filter;
    }
    if ($employee_id_filter) {
        $conditions[] = "lr.EmployeeID = :employee_id";
        $params[':employee_id'] = $employee_id_filter;
    }
    if ($status_filter && $status_filter !== 'all') {
        $conditions[] = "lr.Status = :status";
        $params[':status'] = $status_filter;
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    $sql .= " ORDER BY lr.RequestDate DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($leave_requests as $request) {
        $row = $request;
        $row['StartDateFormatted'] = !empty($request['StartDate']) ? date('M d, Y', strtotime($request['StartDate'])) : 'N/A';
        $row['EndDateFormatted'] = !empty($request['EndDate']) ? date('M d, Y', strtotime($request['EndDate'])) : 'N/A';
        $row['RequestDateFormatted'] = !empty($request['RequestDate']) ? date('M d, Y H:i', strtotime($request['RequestDate'])) : 'N/A';
        $row['ApprovalDateFormatted'] = !empty($request['ApprovalDate']) ? date('M d, Y H:i', strtotime($request['ApprovalDate'])) : 'N/A';
        $report_data['rows'][] = $row;
    }

    http_response_code(200);
    echo json_encode($report_data);

} catch (\PDOException $e) {
    error_log("PHP PDOException in " . __FILE__ . ": " . $e->getMessage());
    $report_data['error'] = 'Database error generating leave summary report.';
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode($report_data);
} catch (Throwable $e) { 
    error_log("PHP Throwable in " . __FILE__ . ": " . $e->getMessage());
    $report_data['error'] = 'Unexpected server error generating leave summary report.';
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode($report_data);
}
exit; 
?>

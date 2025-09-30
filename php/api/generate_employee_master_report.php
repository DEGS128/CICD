<?php
// hr34/php/api/generate_employee_master_report.php

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0); 
ini_set('log_errors', 1);

session_start(); 

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, OPTIONS'); // Use GET for fetching report data
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

// --- Report Parameters (Placeholder for now, can be expanded) ---
// For an Employee Master List, you might filter by status (active/inactive) or department.
// $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all'; // e.g., 'active', 'inactive', 'all'
// $department_filter = isset($_GET['department_id']) ? filter_var($_GET['department_id'], FILTER_VALIDATE_INT) : null;

$report_data = [
    'reportName' => 'Employee Master List',
    'generatedAt' => date('Y-m-d H:i:s'),
    'columns' => [],
    'rows' => [],
    'error' => null
];

try {
    // Define the columns for the report
    $report_data['columns'] = [
        ['key' => 'EmployeeID', 'label' => 'Emp. ID'],
        ['key' => 'FullName', 'label' => 'Full Name'],
        ['key' => 'Email', 'label' => 'Email'],
        ['key' => 'PhoneNumber', 'label' => 'Phone'],
        ['key' => 'JobTitle', 'label' => 'Job Title'],
        ['key' => 'DepartmentName', 'label' => 'Department'],
        ['key' => 'HireDateFormatted', 'label' => 'Hire Date'],
        ['key' => 'Status', 'label' => 'Status']
    ];

    // SQL to fetch employee data
    $sql = "SELECT 
                e.EmployeeID,
                CONCAT(e.FirstName, ' ', e.LastName) AS FullName,
                e.Email,
                e.PhoneNumber,
                e.JobTitle,
                os.DepartmentName,
                e.HireDate,
                CASE WHEN e.IsActive = 1 THEN 'Active' ELSE 'Inactive' END AS Status
            FROM Employees e
            LEFT JOIN OrganizationalStructure os ON e.DepartmentID = os.DepartmentID
            ORDER BY e.LastName, e.FirstName";
    
    // Add WHERE clauses here if filters are implemented
    // For example:
    // if ($status_filter !== 'all') {
    //     $sql .= " WHERE e.IsActive = " . ($status_filter === 'active' ? 1 : 0);
    // }

    $stmt = $pdo->query($sql);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($employees as $employee) {
        $row = $employee; // Start with all fetched data
        $row['HireDateFormatted'] = !empty($employee['HireDate']) ? date('M d, Y', strtotime($employee['HireDate'])) : 'N/A';
        $report_data['rows'][] = $row;
    }

    http_response_code(200);
    echo json_encode($report_data);

} catch (\PDOException $e) {
    error_log("PHP PDOException in " . __FILE__ . ": " . $e->getMessage());
    $report_data['error'] = 'Database error generating employee master list.';
    if (!headers_sent()) { 
        header('Content-Type: application/json'); 
        http_response_code(500); 
    }
    echo json_encode($report_data);
} catch (Throwable $e) { 
    error_log("PHP Throwable in " . __FILE__ . ": " . $e->getMessage());
    $report_data['error'] = 'Unexpected server error generating employee master list.';
    if (!headers_sent()) { 
        header('Content-Type: application/json'); 
        http_response_code(500); 
    }
    echo json_encode($report_data);
}
exit; 
?>

<?php
// hr34/php/api/get_hr_reports_list.php

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

// --- Database Connection (Not strictly needed for this placeholder, but good practice to include) ---
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
     echo json_encode(['error' => 'Permission denied. You do not have rights to view reports list.']);
     exit;
}

// --- Placeholder for Report List ---
// In a real application, you might query the HRReports table or have a predefined list of report templates.
$available_reports = [
    [
        'reportId' => 'employee_master_list',
        'reportName' => 'Employee Master List',
        'description' => 'Full list of all active and inactive employees with key details.',
        'defaultParameters' => ['status' => 'all', 'department' => 'all'],
        'generationEndpoint' => 'generate_employee_master_report.php' // Example endpoint
    ],
    [
        'reportId' => 'leave_summary_report',
        'reportName' => 'Leave Summary Report',
        'description' => 'Summary of leave taken by employees within a specified period.',
        'defaultParameters' => ['period' => 'current_year', 'department' => 'all'],
        'generationEndpoint' => 'generate_leave_summary_report.php' // Example endpoint
    ],
    [
        'reportId' => 'payroll_summary_report',
        'reportName' => 'Payroll Summary Report',
        'description' => 'Summary of payroll costs for a specified period or payroll run.',
        'defaultParameters' => ['period' => 'last_month'],
        'generationEndpoint' => 'generate_payroll_summary_report.php' // Example endpoint
    ],
    // Add more report definitions here
];

try {
    // For now, just return the static list.
    // Later, you could query a database table `HRReports` for generated reports
    // or have a more dynamic way of listing available report templates.

    http_response_code(200);
    echo json_encode($available_reports);

} catch (Throwable $e) { 
    error_log("PHP Throwable in " . __FILE__ . ": " . $e->getMessage());
    if (!headers_sent()) { 
        header('Content-Type: application/json'); 
        http_response_code(500); 
    }
    echo json_encode(['error' => 'Unexpected server error retrieving reports list.']);
}
exit; 
?>

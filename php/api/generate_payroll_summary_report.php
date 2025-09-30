<?php
// hr34/php/api/generate_payroll_summary_report.php

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
// Example: ?payroll_run_id=X&department_id=Y
$payroll_run_id_filter = isset($_GET['payroll_run_id']) ? filter_var($_GET['payroll_run_id'], FILTER_VALIDATE_INT) : null;
$department_id_filter = isset($_GET['department_id']) ? filter_var($_GET['department_id'], FILTER_VALIDATE_INT) : null;
// You could also add date range filters for PaymentDate of PayrollRuns

$report_data = [
    'reportName' => 'Payroll Summary Report',
    'generatedAt' => date('Y-m-d H:i:s'),
    'columns' => [],
    'rows' => [],
    'summary' => [], // For overall totals
    'error' => null
];

try {
    $report_data['columns'] = [
        ['key' => 'PayslipID', 'label' => 'Payslip ID'],
        ['key' => 'PayrollID', 'label' => 'Payroll Run ID'],
        ['key' => 'EmployeeName', 'label' => 'Employee'],
        ['key' => 'DepartmentName', 'label' => 'Department'],
        ['key' => 'PayPeriod', 'label' => 'Pay Period'],
        ['key' => 'PaymentDateFormatted', 'label' => 'Payment Date'],
        ['key' => 'GrossIncomeFormatted', 'label' => 'Gross Income'],
        ['key' => 'TotalDeductionsFormatted', 'label' => 'Total Deductions'],
        ['key' => 'NetIncomeFormatted', 'label' => 'Net Income']
    ];

    $sql = "SELECT
                ps.PayslipID,
                ps.PayrollID,
                CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                os.DepartmentName,
                ps.PayPeriodStartDate,
                ps.PayPeriodEndDate,
                ps.PaymentDate,
                ps.GrossIncome,
                ps.TotalDeductions,
                ps.NetIncome
            FROM Payslips ps
            JOIN Employees e ON ps.EmployeeID = e.EmployeeID
            JOIN PayrollRuns pr ON ps.PayrollID = pr.PayrollID
            LEFT JOIN OrganizationalStructure os ON e.DepartmentID = os.DepartmentID";

    $conditions = [];
    $params = [];

    if ($payroll_run_id_filter) {
        $conditions[] = "ps.PayrollID = :payroll_run_id";
        $params[':payroll_run_id'] = $payroll_run_id_filter;
    }
    if ($department_id_filter) {
        $conditions[] = "e.DepartmentID = :department_id";
        $params[':department_id'] = $department_id_filter;
    }
    // Add date range filters for pr.PaymentDate if needed

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    $sql .= " ORDER BY pr.PaymentDate DESC, ps.PayrollID, e.LastName, e.FirstName";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_gross = 0;
    $total_deductions_sum = 0;
    $total_net = 0;

    foreach ($payslips as $payslip) {
        $row = $payslip;
        $row['PayPeriod'] = (!empty($payslip['PayPeriodStartDate']) ? date('M d, Y', strtotime($payslip['PayPeriodStartDate'])) : 'N/A') . ' - ' . 
                            (!empty($payslip['PayPeriodEndDate']) ? date('M d, Y', strtotime($payslip['PayPeriodEndDate'])) : 'N/A');
        $row['PaymentDateFormatted'] = !empty($payslip['PaymentDate']) ? date('M d, Y', strtotime($payslip['PaymentDate'])) : 'N/A';
        
        $row['GrossIncomeFormatted'] = number_format((float)$payslip['GrossIncome'], 2);
        $row['TotalDeductionsFormatted'] = number_format((float)$payslip['TotalDeductions'], 2);
        $row['NetIncomeFormatted'] = number_format((float)$payslip['NetIncome'], 2);
        
        $report_data['rows'][] = $row;

        $total_gross += (float)$payslip['GrossIncome'];
        $total_deductions_sum += (float)$payslip['TotalDeductions'];
        $total_net += (float)$payslip['NetIncome'];
    }
    
    $report_data['summary'] = [
        ['label' => 'Total Gross Income', 'value' => number_format($total_gross, 2)],
        ['label' => 'Total Deductions', 'value' => number_format($total_deductions_sum, 2)],
        ['label' => 'Total Net Income Paid', 'value' => number_format($total_net, 2)],
        ['label' => 'Total Payslips Processed', 'value' => count($payslips)]
    ];


    http_response_code(200);
    echo json_encode($report_data);

} catch (\PDOException $e) {
    error_log("PHP PDOException in " . __FILE__ . ": " . $e->getMessage());
    $report_data['error'] = 'Database error generating payroll summary report.';
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode($report_data);
} catch (Throwable $e) { 
    error_log("PHP Throwable in " . __FILE__ . ": " . $e->getMessage());
    $report_data['error'] = 'Unexpected server error generating payroll summary report.';
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode($report_data);
}
exit; 
?>

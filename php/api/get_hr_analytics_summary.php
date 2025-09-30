<?php
// hr34/php/api/get_hr_analytics_summary.php

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0); 
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log'); 

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
    error_log("PHP Error in get_hr_analytics_summary.php (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { 
        header('Content-Type: application/json'); 
        http_response_code(500); 
    }
    echo json_encode(['error' => 'Server configuration error: Could not connect to the database.']);
    exit;
}

// --- Authorization Check ---
$allowed_roles = [1, 2]; 
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || (!in_array((int)$_SESSION['role_id'], $allowed_roles) && $_SESSION['user_id'] !== -1)) {
     http_response_code(403); 
     echo json_encode(['error' => 'Permission denied. You do not have rights to view HR analytics.']);
     exit;
}

$analytics_summary = [
    'totalActiveEmployees' => 0,
    'headcountByDepartment' => [],
    'totalLeaveDaysRequestedThisYear' => 0,
    'totalPayrollCostLastRun' => 0,
    'lastPayrollRunIdForCost' => null,
    'averageTenureYears' => 0, // New KPI
    'totalLeaveTypes' => 0,      // New KPI
    'leaveDaysByTypeThisYear' => [], // New Chart Data
    'error' => null 
];

try {
    // 1. Total Active Employees
    $stmt_total_employees = $pdo->query("SELECT COUNT(*) FROM Employees WHERE IsActive = TRUE");
    if ($stmt_total_employees) {
        $analytics_summary['totalActiveEmployees'] = (int)$stmt_total_employees->fetchColumn();
    } else {
        throw new PDOException("Failed to execute query for total active employees.");
    }

    // 2. Headcount by Department (for active employees)
    $sql_headcount_dept = "SELECT os.DepartmentName, COUNT(e.EmployeeID) as Headcount
                           FROM Employees e
                           JOIN OrganizationalStructure os ON e.DepartmentID = os.DepartmentID
                           WHERE e.IsActive = TRUE
                           GROUP BY os.DepartmentID, os.DepartmentName
                           ORDER BY Headcount DESC";
    $stmt_headcount_dept = $pdo->query($sql_headcount_dept);
    if ($stmt_headcount_dept) {
        $analytics_summary['headcountByDepartment'] = $stmt_headcount_dept->fetchAll(PDO::FETCH_ASSOC);
    } else {
        throw new PDOException("Failed to execute query for headcount by department.");
    }
    
    // 3. Total Approved Leave Days Requested This Year
    $current_year = date('Y');
    $sql_leave_days = "SELECT SUM(NumberOfDays) as TotalDays
                       FROM LeaveRequests
                       WHERE Status = 'Approved' AND YEAR(StartDate) = :year";
    $stmt_leave_days = $pdo->prepare($sql_leave_days);
    $stmt_leave_days->bindParam(':year', $current_year, PDO::PARAM_INT);
    $stmt_leave_days->execute();
    $total_leave_days = $stmt_leave_days->fetchColumn();
    $analytics_summary['totalLeaveDaysRequestedThisYear'] = $total_leave_days ? (float)$total_leave_days : 0;

    // 4. Total Payroll Cost for the Last Completed Payroll Run
    $sql_last_run = "SELECT PayrollID FROM PayrollRuns WHERE Status = 'Completed' ORDER BY ProcessedDate DESC LIMIT 1";
    $stmt_last_run = $pdo->query($sql_last_run);
    if ($stmt_last_run) {
        $last_payroll_id = $stmt_last_run->fetchColumn();
        if ($last_payroll_id) {
            $analytics_summary['lastPayrollRunIdForCost'] = (int)$last_payroll_id;
            $sql_payroll_cost = "SELECT SUM(NetIncome) as TotalCost FROM Payslips WHERE PayrollID = :payroll_id";
            $stmt_payroll_cost = $pdo->prepare($sql_payroll_cost);
            $stmt_payroll_cost->bindParam(':payroll_id', $last_payroll_id, PDO::PARAM_INT);
            $stmt_payroll_cost->execute();
            $total_cost = $stmt_payroll_cost->fetchColumn();
            $analytics_summary['totalPayrollCostLastRun'] = $total_cost ? (float)$total_cost : 0;
        } else {
            $analytics_summary['totalPayrollCostLastRun'] = 0;
            $analytics_summary['lastPayrollRunIdForCost'] = null;
        }
    } else {
         throw new PDOException("Failed to execute query for last payroll run.");
    }

    // 5. Average Active Employee Tenure (in years)
    // Calculates the average tenure based on HireDate for active employees.
    $sql_avg_tenure = "SELECT AVG(DATEDIFF(CURDATE(), HireDate) / 365.25) as AvgTenure
                       FROM Employees
                       WHERE IsActive = TRUE AND HireDate IS NOT NULL";
    $stmt_avg_tenure = $pdo->query($sql_avg_tenure);
    if ($stmt_avg_tenure) {
        $avg_tenure = $stmt_avg_tenure->fetchColumn();
        $analytics_summary['averageTenureYears'] = $avg_tenure ? round((float)$avg_tenure, 1) : 0;
    } else {
        throw new PDOException("Failed to execute query for average employee tenure.");
    }

    // 6. Total Number of Configured Leave Types
    // Counts the distinct types of leave available.
    $stmt_total_leave_types = $pdo->query("SELECT COUNT(*) FROM LeaveTypes WHERE IsActive = TRUE");
    if ($stmt_total_leave_types) {
        $analytics_summary['totalLeaveTypes'] = (int)$stmt_total_leave_types->fetchColumn();
    } else {
        throw new PDOException("Failed to execute query for total leave types.");
    }

    // 7. Approved Leave Days by Type This Year (for Pie Chart)
    // Gathers data on the number of approved leave days for each leave type within the current year.
    $sql_leave_by_type = "SELECT lt.TypeName, SUM(lr.NumberOfDays) as TotalDays
                          FROM LeaveRequests lr
                          JOIN LeaveTypes lt ON lr.LeaveTypeID = lt.LeaveTypeID
                          WHERE lr.Status = 'Approved' AND YEAR(lr.StartDate) = :year
                          GROUP BY lt.LeaveTypeID, lt.TypeName
                          HAVING SUM(lr.NumberOfDays) > 0
                          ORDER BY TotalDays DESC";
    $stmt_leave_by_type = $pdo->prepare($sql_leave_by_type);
    $stmt_leave_by_type->bindParam(':year', $current_year, PDO::PARAM_INT);
    $stmt_leave_by_type->execute();
    if ($stmt_leave_by_type) {
        $analytics_summary['leaveDaysByTypeThisYear'] = $stmt_leave_by_type->fetchAll(PDO::FETCH_ASSOC);
    } else {
        throw new PDOException("Failed to execute query for leave days by type.");
    }

    http_response_code(200);
    echo json_encode($analytics_summary);

} catch (\PDOException $e) {
    error_log("PHP PDOException in get_hr_analytics_summary.php: " . $e->getMessage());
    $analytics_summary['error'] = 'Database error retrieving HR analytics summary.';
    if (!headers_sent()) { 
        header('Content-Type: application/json'); 
        http_response_code(500); 
    }
    echo json_encode($analytics_summary); 
} catch (Throwable $e) { 
    error_log("PHP Throwable in get_hr_analytics_summary.php: " . $e->getMessage());
    $analytics_summary['error'] = 'Unexpected server error retrieving HR analytics summary.';
    if (!headers_sent()) { 
        header('Content-Type: application/json'); 
        http_response_code(500); 
    }
    echo json_encode($analytics_summary); 
}
exit; 
?>

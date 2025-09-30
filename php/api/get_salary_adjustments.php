<?php
// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep errors hidden from user in production
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log'); // Optional custom log file

session_start(); // Required for potential authorization checks if added later

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Database Connection ---
$pdo = null;
try {
    require_once '../db_connect.php'; // Path relative to this api script
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('Database connection object ($pdo) not properly created by db_connect.php.');
    }
} catch (Throwable $e) {
    error_log("PHP Error in get_salary_adjustments.php (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Server configuration error: Could not connect to the database.']);
    exit;
}

// --- Authorization Check (Placeholder - Implement as needed) ---
// Example: Only allow HR Admins or System Admins to view all salary adjustments
/*
if (!isset($_SESSION['role_id']) || !in_array((int)$_SESSION['role_id'], [1, 2])) { // Assuming 1=SysAdmin, 2=HR Admin
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Permission denied to view salary adjustments.']);
    exit;
}
*/
// --- End Authorization Check ---

// --- Optional Filters ---
$employee_id_filter = isset($_GET['employee_id']) ? filter_var($_GET['employee_id'], FILTER_VALIDATE_INT) : null;
// --- End Filters ---

try {
    $sql = "SELECT
                sa.AdjustmentID,
                sa.EmployeeID,
                CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                sa.AdjustmentDate,
                sa.Reason,
                sa.PercentageIncrease,
                sa.ApprovedBy AS ApprovedByEmployeeID,
                CONCAT(approver.FirstName, ' ', approver.LastName) AS ApproverName,
                sa.ApprovalDate,
                prev_sal.BaseSalary AS PreviousBaseSalary,
                prev_sal.PayFrequency AS PreviousPayFrequency,
                new_sal.BaseSalary AS NewBaseSalary,
                new_sal.PayFrequency AS NewPayFrequency
            FROM
                SalaryAdjustments sa
            JOIN
                Employees e ON sa.EmployeeID = e.EmployeeID
            LEFT JOIN
                Employees approver ON sa.ApprovedBy = approver.EmployeeID
            LEFT JOIN
                EmployeeSalaries prev_sal ON sa.PreviousSalaryID = prev_sal.SalaryID
            JOIN
                EmployeeSalaries new_sal ON sa.NewSalaryID = new_sal.SalaryID";

    $conditions = [];
    $params = [];

    if ($employee_id_filter !== null && $employee_id_filter > 0) {
        $conditions[] = "sa.EmployeeID = :employee_id";
        $params[':employee_id'] = $employee_id_filter;
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY sa.AdjustmentDate DESC, e.LastName, e.FirstName";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates and monetary values
    foreach ($adjustments as &$adj) {
        if (!empty($adj['AdjustmentDate'])) {
            $adj['AdjustmentDateFormatted'] = date('M d, Y', strtotime($adj['AdjustmentDate']));
        }
        if (!empty($adj['ApprovalDate'])) {
            $adj['ApprovalDateFormatted'] = date('M d, Y H:i', strtotime($adj['ApprovalDate']));
        }
        $adj['PercentageIncrease'] = isset($adj['PercentageIncrease']) ? number_format((float)$adj['PercentageIncrease'], 2) . '%' : '-';
        $adj['PreviousBaseSalary'] = isset($adj['PreviousBaseSalary']) ? number_format((float)$adj['PreviousBaseSalary'], 2) : '-';
        $adj['NewBaseSalary'] = isset($adj['NewBaseSalary']) ? number_format((float)$adj['NewBaseSalary'], 2) : '-';
    }
    unset($adj);

    http_response_code(200);
    echo json_encode($adjustments);

} catch (\PDOException $e) {
    error_log("PHP PDOException in get_salary_adjustments.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error retrieving salary adjustments.']);
} catch (Throwable $e) {
    error_log("PHP Throwable in get_salary_adjustments.php: " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Unexpected server error retrieving salary adjustments.']);
}
exit;
?>

<?php
// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production

// --- Database Connection ---
$pdo = null;
try {
    require_once '../db_connect.php';
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('DB connection failed');
    }
} catch (Throwable $e) {
    error_log("PHP Error in get_payslips.php (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Server configuration error.']);
    exit;
}

// --- Filters ---
$employee_id_filter = isset($_GET['employee_id']) ? filter_var($_GET['employee_id'], FILTER_VALIDATE_INT) : null;
$payroll_id_filter = isset($_GET['payroll_id']) ? filter_var($_GET['payroll_id'], FILTER_VALIDATE_INT) : null;
$start_date_filter = isset($_GET['start_date']) ? $_GET['start_date'] : null; // Filter by Payment Date >=
$end_date_filter = isset($_GET['end_date']) ? $_GET['end_date'] : null;     // Filter by Payment Date <=
// --- End Filters ---

// --- Fetch Logic ---
$sql = '';
$params = [];
try {
    // Select key payslip details and join with Employees and PayrollRuns
    $sql = "SELECT
                p.PayslipID,
                p.PayrollID,
                p.EmployeeID,
                CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                p.PayPeriodStartDate,
                p.PayPeriodEndDate,
                p.PaymentDate,
                p.GrossIncome,
                p.TotalDeductions,
                p.NetIncome,
                pr.Status as PayrollRunStatus
            FROM
                Payslips p
            JOIN
                Employees e ON p.EmployeeID = e.EmployeeID
            JOIN
                PayrollRuns pr ON p.PayrollID = pr.PayrollID";

    $conditions = [];
    // $params initialized above

    if ($employee_id_filter !== null && $employee_id_filter > 0) {
        $conditions[] = "p.EmployeeID = :employee_id";
        $params[':employee_id'] = $employee_id_filter;
    }
    if ($payroll_id_filter !== null && $payroll_id_filter > 0) {
        $conditions[] = "p.PayrollID = :payroll_id";
        $params[':payroll_id'] = $payroll_id_filter;
    }
    if (!empty($start_date_filter) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date_filter)) {
         $conditions[] = "p.PaymentDate >= :start_date";
         $params[':start_date'] = $start_date_filter;
    }
     if (!empty($end_date_filter) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date_filter)) {
         $conditions[] = "p.PaymentDate <= :end_date";
         $params[':end_date'] = $end_date_filter;
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY p.PaymentDate DESC, e.LastName, e.FirstName"; // Show most recent first

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format data (optional)
    foreach ($payslips as &$ps) {
        if (!empty($ps['PayPeriodStartDate'])) $ps['PayPeriodStartDateFormatted'] = date('M d, Y', strtotime($ps['PayPeriodStartDate']));
        if (!empty($ps['PayPeriodEndDate'])) $ps['PayPeriodEndDateFormatted'] = date('M d, Y', strtotime($ps['PayPeriodEndDate']));
        if (!empty($ps['PaymentDate'])) $ps['PaymentDateFormatted'] = date('M d, Y', strtotime($ps['PaymentDate']));
        if (isset($ps['GrossIncome'])) $ps['GrossIncomeFormatted'] = number_format((float)$ps['GrossIncome'], 2);
        if (isset($ps['TotalDeductions'])) $ps['TotalDeductionsFormatted'] = number_format((float)$ps['TotalDeductions'], 2);
        if (isset($ps['NetIncome'])) $ps['NetIncomeFormatted'] = number_format((float)$ps['NetIncome'], 2);
    }
    unset($ps);

    if (headers_sent()) { exit; }
    http_response_code(200);
    echo json_encode($payslips);

} catch (\PDOException $e) {
    error_log("PHP PDOException in get_payslips.php: " . $e->getMessage() . " | SQL: " . $sql);
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Database error retrieving payslips.']);
} catch (Throwable $e) {
    error_log("PHP Throwable in get_payslips.php: " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Unexpected server error retrieving payslips.']);
}
exit;
?>

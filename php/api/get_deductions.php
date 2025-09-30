<?php
// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production

// --- Database Connection ---
try {
    require_once '../db_connect.php';
} catch (Throwable $e) {
    error_log("Failed to include db_connect.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error.']);
    exit;
}

// --- Optional Filters ---
$employee_id_filter = isset($_GET['employee_id']) ? filter_var($_GET['employee_id'], FILTER_VALIDATE_INT) : null;
$payroll_id_filter = isset($_GET['payroll_id']) ? filter_var($_GET['payroll_id'], FILTER_VALIDATE_INT) : null;
// --- End Filters ---

try {
    // Base SQL query - Join with Employees
    $sql = "SELECT
                d.DeductionID,
                d.EmployeeID,
                CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                d.PayrollID,
                d.DeductionType,
                d.DeductionAmount,
                d.Provider
            FROM
                Deductions d -- Use the correct table name from your SQL schema
            JOIN
                Employees e ON d.EmployeeID = e.EmployeeID";

    $conditions = [];
    $params = [];

    // Add filter conditions
    if ($employee_id_filter !== null && $employee_id_filter > 0) {
        $conditions[] = "d.EmployeeID = :employee_id";
        $params[':employee_id'] = $employee_id_filter;
    }
    if ($payroll_id_filter !== null && $payroll_id_filter > 0) {
        $conditions[] = "d.PayrollID = :payroll_id";
        $params[':payroll_id'] = $payroll_id_filter;
    }

     // Append WHERE clause if conditions exist
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }


    $sql .= " ORDER BY d.PayrollID DESC, e.LastName, e.FirstName, d.DeductionType";

    // Prepare and execute the statement
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Fetch all results
    $deductions = $stmt->fetchAll();

    // Format data (optional)
    foreach ($deductions as &$ded) {
        if (isset($ded['DeductionAmount'])) {
             $ded['DeductionAmountFormatted'] = number_format($ded['DeductionAmount'], 2);
        }
    }
    unset($ded); // Unset reference

    // Output the results as JSON
    echo json_encode($deductions);

} catch (\PDOException $e) {
    error_log("API Error (get_deductions): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve deduction data.']);
}
?>

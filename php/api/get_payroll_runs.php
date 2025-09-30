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

// --- Optional Filters (e.g., by status, date range) ---
// $status_filter = isset($_GET['status']) ? trim(htmlspecialchars($_GET['status'])) : null;
// --- End Filters ---

try {
    // Base SQL query
    $sql = "SELECT
                PayrollID,
                PayPeriodStartDate,
                PayPeriodEndDate,
                PaymentDate,
                Status,
                ProcessedDate
            FROM
                PayrollRuns -- Use the correct table name from your SQL schema
            ORDER BY
                PaymentDate DESC, PayrollID DESC"; // Show most recent first

    $params = [];
    // Add WHERE clause and bind params if filters are implemented

    // Prepare and execute the statement
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Fetch all results
    $payrollRuns = $stmt->fetchAll();

    // Format data (optional)
    foreach ($payrollRuns as &$run) {
        if (!empty($run['PayPeriodStartDate'])) {
             $run['PayPeriodStartDateFormatted'] = date('M d, Y', strtotime($run['PayPeriodStartDate']));
        }
        if (!empty($run['PayPeriodEndDate'])) {
             $run['PayPeriodEndDateFormatted'] = date('M d, Y', strtotime($run['PayPeriodEndDate']));
        }
        if (!empty($run['PaymentDate'])) {
             $run['PaymentDateFormatted'] = date('M d, Y', strtotime($run['PaymentDate']));
        }
         if (!empty($run['ProcessedDate'])) {
             $run['ProcessedDateFormatted'] = date('M d, Y H:i:s', strtotime($run['ProcessedDate']));
        }
    }
    unset($run); // Unset reference

    // Output the results as JSON
    echo json_encode($payrollRuns);

} catch (\PDOException $e) {
    error_log("API Error (get_payroll_runs): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve payroll run data.']);
}
?>

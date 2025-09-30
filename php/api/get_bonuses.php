<?php
// --- Error Reporting for Debugging ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log');
// --- End Error Reporting ---

// --- Set Headers EARLY ---
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
// --- End Headers ---

// --- Database Connection ---
try {
    require_once '../db_connect.php';
} catch (Throwable $e) {
    error_log("Failed to include db_connect.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: Cannot connect to database.']);
    exit;
}
// --- End Database Connection ---

// --- Optional Filters ---
$employee_id_filter = isset($_GET['employee_id']) ? filter_var($_GET['employee_id'], FILTER_VALIDATE_INT) : null;
// --- End Filters ---

try {
    // Base SQL query - Join with Employees
    $sql = "SELECT
                b.BonusID,
                b.EmployeeID,
                CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                b.PayrollID, -- Optional link to payroll run
                b.BonusAmount,
                b.BonusType,
                b.AwardDate,
                b.PaymentDate
            FROM
                Bonuses b
            JOIN
                Employees e ON b.EmployeeID = e.EmployeeID";

    $params = [];

    // Add filter condition if employee ID is provided
    if ($employee_id_filter !== null && $employee_id_filter > 0) {
        $sql .= " WHERE b.EmployeeID = :employee_id";
        $params[':employee_id'] = $employee_id_filter;
    }

    $sql .= " ORDER BY b.AwardDate DESC, e.LastName, e.FirstName"; // Order by most recent award date

    // Prepare and execute the statement
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Fetch all results
    $bonuses = $stmt->fetchAll();

    // Format data (optional)
    foreach ($bonuses as &$bonus) {
        if (!empty($bonus['AwardDate'])) {
             $bonus['AwardDateFormatted'] = date('M d, Y', strtotime($bonus['AwardDate']));
        }
         if (!empty($bonus['PaymentDate'])) {
             $bonus['PaymentDateFormatted'] = date('M d, Y', strtotime($bonus['PaymentDate']));
        } else {
            $bonus['PaymentDateFormatted'] = '-';
        }
        // Format currency
        if (isset($bonus['BonusAmount'])) {
             $bonus['BonusAmountFormatted'] = number_format($bonus['BonusAmount'], 2);
        }
    }
    unset($bonus); // Unset reference

    // Output the results as JSON
    echo json_encode($bonuses);

} catch (\PDOException $e) {
    // Log the error
    error_log("API Error (get_bonuses): " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Failed to retrieve bonus data.']);
}
?>

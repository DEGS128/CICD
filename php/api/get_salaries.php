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

// --- Optional Filters (Example: by employee_id) ---
$employee_id_filter = isset($_GET['employee_id']) ? filter_var($_GET['employee_id'], FILTER_VALIDATE_INT) : null;
// --- End Filters ---

try {
    // Base SQL query - Fetch only the *current* salary record for each employee
    $sql = "SELECT
                es.SalaryID,
                es.EmployeeID,
                CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                e.JobTitle,
                es.BaseSalary,
                es.PayFrequency,
                es.PayRate,
                es.EffectiveDate
            FROM
                EmployeeSalaries es
            JOIN
                Employees e ON es.EmployeeID = e.EmployeeID
            WHERE
                es.IsCurrent = TRUE"; // Filter for only current records

    $params = [];

    // Add filter condition if employee ID is provided
    if ($employee_id_filter !== null && $employee_id_filter > 0) {
        $sql .= " AND es.EmployeeID = :employee_id";
        $params[':employee_id'] = $employee_id_filter;
    }

    $sql .= " ORDER BY e.LastName, e.FirstName"; // Order by employee name

    // Prepare and execute the statement
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Fetch all results
    $salaries = $stmt->fetchAll();

    // Format data (optional)
    foreach ($salaries as &$salary) {
        if (!empty($salary['EffectiveDate'])) {
             $salary['EffectiveDateFormatted'] = date('M d, Y', strtotime($salary['EffectiveDate']));
        }
        // Format currency/numbers if needed
        if (isset($salary['BaseSalary'])) {
             $salary['BaseSalaryFormatted'] = number_format($salary['BaseSalary'], 2);
        }
         if (isset($salary['PayRate'])) {
             $salary['PayRateFormatted'] = $salary['PayRate'] !== null ? number_format($salary['PayRate'], 2) : '-';
        }
    }
    unset($salary); // Unset reference

    // Output the results as JSON
    echo json_encode($salaries);

} catch (\PDOException $e) {
    // Log the error
    error_log("API Error (get_salaries): " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Failed to retrieve salary data.']);
}
?>

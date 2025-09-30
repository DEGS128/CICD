<?php
// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log');

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
    error_log("PHP Error in get_leave_balances.php (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Server configuration error.']);
    exit;
}

// --- Filters ---
$employee_id_filter = isset($_GET['employee_id']) ? filter_var($_GET['employee_id'], FILTER_VALIDATE_INT) : null;
$year_filter = isset($_GET['year']) ? filter_var($_GET['year'], FILTER_VALIDATE_INT) : null;

// Default to current year if not provided or invalid
if (empty($year_filter) || $year_filter === false) {
    $year_filter = date('Y');
}

// Employee ID is mandatory for fetching balances
if (empty($employee_id_filter) || $employee_id_filter <= 0) {
    if (!headers_sent()) { http_response_code(400); }
    echo json_encode(['error' => 'Valid Employee ID is required to fetch balances.']);
    exit;
}
// --- End Filters ---

// --- Fetch Logic ---
$sql = '';
$params = [];
try {
    // Fetch balances for the specified employee and year
    $sql = "SELECT
                lb.BalanceID,
                lb.EmployeeID,
                lb.LeaveTypeID,
                lt.TypeName AS LeaveTypeName,
                lb.BalanceYear,
                lb.EntitledDays,
                lb.AccruedDays,
                lb.UsedDays,
                lb.AvailableDays -- This is a generated column in the schema
            FROM
                LeaveBalances lb -- Ensure table name matches schema
            JOIN
                LeaveTypes lt ON lb.LeaveTypeID = lt.LeaveTypeID
            WHERE
                lb.EmployeeID = :employee_id
                AND lb.BalanceYear = :balance_year
            ORDER BY
                lt.TypeName";

    $params = [
        ':employee_id' => $employee_id_filter,
        ':balance_year' => $year_filter
    ];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Final JSON Output ---
    if (headers_sent()) { exit; }
    http_response_code(200);
    // Return the balances array (can be empty if no records found)
    echo json_encode($balances);

} catch (\PDOException $e) {
    error_log("PHP PDOException in get_leave_balances.php: " . $e->getMessage() . " | SQL: " . $sql);
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Database error retrieving leave balances.']);
} catch (Throwable $e) {
    error_log("PHP Throwable in get_leave_balances.php: " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Unexpected server error retrieving leave balances.']);
}
exit;
?>

<?php
// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep errors hidden from user in production
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log'); // Optional custom log file

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
    error_log("PHP Error in get_incentives.php (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Server configuration error: Could not connect to the database.']);
    exit;
}

// --- Optional Filters ---
$employee_id_filter = isset($_GET['employee_id']) ? filter_var($_GET['employee_id'], FILTER_VALIDATE_INT) : null;

try {
    // --- SQL Query with Employee and Compensation Plan Joins ---
    $sql = "SELECT
                i.IncentiveID,
                i.EmployeeID,
                CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                i.PlanID, 
                cp.PlanName AS CompensationPlanName,
                i.IncentiveType,
                i.Amount,
                i.AwardDate,
                i.PayoutDate, -- CORRECTED: Was i.PaymentDate
                i.PayrollID
            FROM
                incentives i
            JOIN
                employees e ON i.EmployeeID = e.EmployeeID
            LEFT JOIN
                compensationplans cp ON i.PlanID = cp.PlanID
            ";

    $params = [];
    $conditions = [];

    if ($employee_id_filter !== null && $employee_id_filter > 0) {
        $conditions[] = "i.EmployeeID = :employee_id";
        $params[':employee_id'] = $employee_id_filter;
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY i.AwardDate DESC, e.LastName, e.FirstName";
    
    error_log("[INFO] get_incentives.php SQL: " . $sql);
    error_log("[INFO] get_incentives.php Params: " . print_r($params, true));


    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $incentives = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatting for the data
    foreach ($incentives as &$inc) {
        if (!empty($inc['AwardDate'])) {
            $inc['AwardDateFormatted'] = date('M d, Y', strtotime($inc['AwardDate']));
        }
        // Use PayoutDate for formatting
        if (!empty($inc['PayoutDate'])) { 
            $inc['PayoutDateFormatted'] = date('M d, Y', strtotime($inc['PayoutDate']));
        } else {
            $inc['PayoutDateFormatted'] = '-'; 
        }
        if (isset($inc['Amount'])) {
            $inc['AmountFormatted'] = number_format((float)$inc['Amount'], 2);
        }
    }
    unset($inc); 

    http_response_code(200);
    echo json_encode($incentives);

} catch (\PDOException $e) {
    error_log("PHP PDOException in get_incentives.php: " . $e->getMessage() . " | SQL: " . $sql);
    http_response_code(500);
    echo json_encode(['error' => 'Database error retrieving incentives.', 'pdo_message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log("PHP Throwable in get_incentives.php: " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Unexpected server error retrieving incentives.', 'general_message' => $e->getMessage()]);
}
exit;
?>

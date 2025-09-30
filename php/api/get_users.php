<?php
/**
 * API Endpoint: Get Users
 * Retrieves a list of system users with their associated employee and role details.
 * Optionally filters by RoleID.
 */

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log');

session_start(); // Needed for authorization check

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
    require_once '../db_connect.php';
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('DB connection failed');
    }
} catch (Throwable $e) {
    error_log("PHP Error in get_users.php (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Server configuration error.']);
    exit;
}

// --- Authorization Check ---
// Only allow System Admins to view the user list
$allowed_roles = [1]; // RoleID 1 = System Admin
if (!isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], $allowed_roles)) {
     http_response_code(403); // Forbidden
     echo json_encode(['error' => 'Permission denied. You do not have rights to view users.']);
     exit;
}
// --- End Auth Check ---


// --- Optional Filters ---
$role_id_filter = isset($_GET['role_id']) ? filter_var($_GET['role_id'], FILTER_VALIDATE_INT) : null;
// --- End Filters ---

// --- Fetch Logic ---
$sql = '';
$params = [];
try {
    $sql = "SELECT
                u.UserID,
                u.EmployeeID,
                u.Username,
                u.RoleID,
                u.IsActive,
                r.RoleName,
                CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                e.JobTitle AS EmployeeJobTitle,
                e.Email AS EmployeeEmail
            FROM
                Users u
            JOIN
                Roles r ON u.RoleID = r.RoleID
            JOIN
                Employees e ON u.EmployeeID = e.EmployeeID";

    $conditions = [];

    if ($role_id_filter !== null && $role_id_filter > 0) {
        $conditions[] = "u.RoleID = :role_id";
        $params[':role_id'] = $role_id_filter;
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY e.LastName, e.FirstName"; // Order by employee name

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Final JSON Output ---
    if (headers_sent()) { exit; }
    http_response_code(200);
    echo json_encode($users);

} catch (\PDOException $e) {
    error_log("PHP PDOException in get_users.php: " . $e->getMessage() . " | SQL: " . $sql);
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Database error retrieving users.']);
} catch (Throwable $e) {
    error_log("PHP Throwable in get_users.php: " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Unexpected server error retrieving users.']);
}
exit;
?>

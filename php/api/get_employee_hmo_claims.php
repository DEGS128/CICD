<?php
/**
 * API Endpoint: Get Employee HMO Claims
 * Retrieves HMO claims for the current logged-in employee
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../db_connect.php';

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

$employeeId = $_SESSION['employee_id'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

try {
    $sql = "SELECT
                hc.ClaimID,
                hc.ClaimType,
                hc.ClaimDate,
                hc.Amount,
                hc.Description,
                hc.Status,
                hc.SubmittedDate,
                hc.ApprovedDate,
                hc.Comments,
                hp.PlanName,
                hpr.ProviderName
            FROM HMOClaims hc
            LEFT JOIN EmployeeHMOEnrollments ehe ON hc.EnrollmentID = ehe.EnrollmentID
            LEFT JOIN HMOPlans hp ON ehe.PlanID = hp.PlanID
            LEFT JOIN HMOProviders hpr ON hp.ProviderID = hpr.ProviderID
            WHERE hc.EmployeeID = :employeeId
            ORDER BY hc.SubmittedDate DESC
            LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':employeeId', $employeeId, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'claims' => $claims
    ]);

} catch (PDOException $e) {
    error_log("Get Employee HMO Claims Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while fetching HMO claims.']);
} catch (Exception $e) {
    error_log("Get Employee HMO Claims Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
exit;
?>

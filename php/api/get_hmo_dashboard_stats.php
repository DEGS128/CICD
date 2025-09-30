<?php
/**
 * API Endpoint: Get HMO Dashboard Statistics
 * Retrieves dashboard statistics for HMO management
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

try {
    // Get total providers
    $sql = "SELECT COUNT(*) as total FROM HMOProviders WHERE IsActive = 1";
    $stmt = $pdo->query($sql);
    $totalProviders = $stmt->fetchColumn();

    // Get active plans
    $sql = "SELECT COUNT(*) as total FROM HMOPlans WHERE IsActive = 1";
    $stmt = $pdo->query($sql);
    $activePlans = $stmt->fetchColumn();

    // Get enrolled employees
    $sql = "SELECT COUNT(DISTINCT EmployeeID) as total FROM EmployeeHMOEnrollments WHERE Status = 'Active'";
    $stmt = $pdo->query($sql);
    $enrolledEmployees = $stmt->fetchColumn();

    // Get pending claims
    $sql = "SELECT COUNT(*) as total FROM HMOClaims WHERE Status = 'Submitted'";
    $stmt = $pdo->query($sql);
    $pendingClaims = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'stats' => [
            'totalProviders' => (int)$totalProviders,
            'activePlans' => (int)$activePlans,
            'enrolledEmployees' => (int)$enrolledEmployees,
            'pendingClaims' => (int)$pendingClaims
        ]
    ]);

} catch (PDOException $e) {
    error_log("Get HMO Dashboard Stats Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while fetching dashboard statistics.']);
} catch (Exception $e) {
    error_log("Get HMO Dashboard Stats Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
exit;
?>

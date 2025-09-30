<?php
/**
 * API Endpoint: Get HMO Enrollments
 * Retrieves all HMO enrollments with employee and plan details
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
    $sql = "SELECT
                eh.EnrollmentID,
                eh.EmployeeID,
                eh.PlanID,
                eh.EnrollmentDate,
                eh.EffectiveDate,
                eh.EndDate,
                eh.Status,
                eh.MonthlyDeduction,
                eh.CreatedAt,
                CONCAT(e.FirstName, ' ', e.LastName) as EmployeeName,
                hp.PlanName,
                hpr.ProviderName
            FROM EmployeeHMOEnrollments eh
            LEFT JOIN employees e ON eh.EmployeeID = e.EmployeeID
            LEFT JOIN HMOPlans hp ON eh.PlanID = hp.PlanID
            LEFT JOIN HMOProviders hpr ON hp.ProviderID = hpr.ProviderID
            ORDER BY eh.CreatedAt DESC";

    $stmt = $pdo->query($sql);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'enrollments' => $enrollments
    ]);

} catch (PDOException $e) {
    error_log("Get HMO Enrollments Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while fetching HMO enrollments.']);
} catch (Exception $e) {
    error_log("Get HMO Enrollments Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
exit;
?>

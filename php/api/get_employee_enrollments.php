<?php
/**
 * API Endpoint: Get Employee HMO Enrollments
 * Retrieves all employee HMO enrollments with employee and plan information
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
                ehe.EnrollmentID,
                ehe.EmployeeID,
                ehe.PlanID,
                ehe.EnrollmentDate,
                ehe.EffectiveDate,
                ehe.EndDate,
                ehe.Status,
                ehe.MonthlyContribution,
                ehe.CompanyContribution,
                ehe.Notes,
                ehe.CreatedAt,
                ehe.UpdatedAt,
                CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                hp.PlanName,
                hpr.ProviderName
            FROM EmployeeHMOEnrollments ehe
            LEFT JOIN Employees e ON ehe.EmployeeID = e.EmployeeID
            LEFT JOIN HMOPlans hp ON ehe.PlanID = hp.PlanID
            LEFT JOIN HMOProviders hpr ON hp.ProviderID = hpr.ProviderID
            ORDER BY ehe.EnrollmentDate DESC";
    
    $stmt = $pdo->query($sql);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'enrollments' => $enrollments
    ]);

} catch (PDOException $e) {
    error_log("Get Employee Enrollments Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while fetching employee enrollments.']);
} catch (Exception $e) {
    error_log("Get Employee Enrollments Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
exit;
?>

<?php
/**
 * API Endpoint: Get HMO Claims
 * Retrieves all HMO claims with employee and dependent information
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
                hc.ClaimID,
                hc.EnrollmentID,
                hc.DependentID,
                hc.ClaimNumber,
                hc.ClaimDate,
                hc.ServiceDate,
                hc.ProviderName,
                hc.ServiceType,
                hc.Diagnosis,
                hc.TreatmentDescription,
                hc.AmountClaimed,
                hc.AmountApproved,
                hc.Status,
                hc.RejectionReason,
                hc.SubmittedAt,
                hc.ProcessedAt,
                hc.Notes,
                hc.ReceiptPath,
                CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                CONCAT(hd.FirstName, ' ', hd.LastName) AS DependentName,
                hp.PlanName,
                hpr.ProviderName AS HMOProviderName
            FROM HMOClaims hc
            LEFT JOIN EmployeeHMOEnrollments ehe ON hc.EnrollmentID = ehe.EnrollmentID
            LEFT JOIN Employees e ON ehe.EmployeeID = e.EmployeeID
            LEFT JOIN HMODependents hd ON hc.DependentID = hd.DependentID
            LEFT JOIN HMOPlans hp ON ehe.PlanID = hp.PlanID
            LEFT JOIN HMOProviders hpr ON hp.ProviderID = hpr.ProviderID
            ORDER BY hc.SubmittedAt DESC";
    
    $stmt = $pdo->query($sql);
    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'claims' => $claims
    ]);

} catch (PDOException $e) {
    error_log("Get HMO Claims Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while fetching HMO claims.']);
} catch (Exception $e) {
    error_log("Get HMO Claims Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
exit;
?>

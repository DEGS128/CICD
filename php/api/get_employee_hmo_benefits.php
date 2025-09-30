<?php
/**
 * API Endpoint: Get Employee HMO Benefits
 * Retrieves HMO benefits for the current logged-in employee
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
$detailed = isset($_GET['detailed']) && $_GET['detailed'] === '1';

try {
    if ($detailed) {
        // Get detailed HMO enrollment information
        $sql = "SELECT
                    eh.EnrollmentID,
                    eh.PlanID,
                    eh.EnrollmentDate,
                    eh.EffectiveDate,
                    eh.EndDate,
                    eh.Status,
                    eh.MonthlyDeduction,
                    hp.PlanName,
                    hp.Description,
                    hp.CoverageType,
                    hp.MonthlyPremium,
                    hp.AnnualLimit,
                    hp.RoomAndBoardLimit,
                    hp.DoctorVisitLimit,
                    hp.EmergencyLimit,
                    hp.EffectiveDate as PlanEffectiveDate,
                    hp.EndDate as PlanEndDate,
                    hpr.ProviderName,
                    hpr.ContactPerson,
                    hpr.PhoneNumber,
                    hpr.Email,
                    hpr.Address,
                    hpr.Website
                FROM EmployeeHMOEnrollments eh
                LEFT JOIN HMOPlans hp ON eh.PlanID = hp.PlanID
                LEFT JOIN HMOProviders hpr ON hp.ProviderID = hpr.ProviderID
                WHERE eh.EmployeeID = :employeeId
                AND eh.Status = 'Active'
                ORDER BY eh.EffectiveDate DESC
                LIMIT 1";
    } else {
        // Get basic HMO enrollment information
        $sql = "SELECT
                    eh.EnrollmentID,
                    eh.PlanID,
                    eh.Status,
                    eh.MonthlyDeduction,
                    hp.PlanName,
                    hp.CoverageType,
                    hp.MonthlyPremium,
                    hpr.ProviderName
                FROM EmployeeHMOEnrollments eh
                LEFT JOIN HMOPlans hp ON eh.PlanID = hp.PlanID
                LEFT JOIN HMOProviders hpr ON hp.ProviderID = hpr.ProviderID
                WHERE eh.EmployeeID = :employeeId
                AND eh.Status = 'Active'
                ORDER BY eh.EffectiveDate DESC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':employeeId', $employeeId, PDO::PARAM_INT);
    $stmt->execute();

    $benefits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'benefits' => $benefits
    ]);

} catch (PDOException $e) {
    error_log("Get Employee HMO Benefits Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while fetching HMO benefits.']);
} catch (Exception $e) {
    error_log("Get Employee HMO Benefits Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
exit;
?>

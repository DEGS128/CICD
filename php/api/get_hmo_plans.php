<?php
/**
 * API Endpoint: Get HMO Plans
 * Retrieves all HMO plans with provider information
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
                hp.PlanID,
                hp.ProviderID,
                hp.PlanName,
                hp.Description,
                hp.CoverageType,
                hp.MonthlyPremium,
                hp.AnnualLimit,
                hp.RoomAndBoardLimit,
                hp.DoctorVisitLimit,
                hp.EmergencyLimit,
                hp.IsActive,
                hp.EffectiveDate,
                hp.EndDate,
                hp.CreatedAt,
                hp.UpdatedAt,
                hpr.ProviderName
            FROM HMOPlans hp
            LEFT JOIN HMOProviders hpr ON hp.ProviderID = hpr.ProviderID
            ORDER BY hpr.ProviderName ASC, hp.PlanName ASC";
    
    $stmt = $pdo->query($sql);
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'plans' => $plans
    ]);

} catch (PDOException $e) {
    error_log("Get HMO Plans Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while fetching HMO plans.']);
} catch (Exception $e) {
    error_log("Get HMO Plans Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
exit;
?>

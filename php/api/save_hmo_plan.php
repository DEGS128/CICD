<?php
/**
 * API Endpoint: Save HMO Plan
 * Creates or updates an HMO plan
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

try {
    $planId = isset($_POST['planId']) ? (int)$_POST['planId'] : null;
    $providerId = (int)$_POST['providerId'];
    $planName = trim($_POST['planName'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $coverageType = trim($_POST['coverageType'] ?? '');
    $monthlyPremium = (float)$_POST['monthlyPremium'];
    $annualLimit = !empty($_POST['annualLimit']) ? (float)$_POST['annualLimit'] : null;
    $roomAndBoardLimit = !empty($_POST['roomAndBoardLimit']) ? (float)$_POST['roomAndBoardLimit'] : null;
    $doctorVisitLimit = !empty($_POST['doctorVisitLimit']) ? (float)$_POST['doctorVisitLimit'] : null;
    $emergencyLimit = !empty($_POST['emergencyLimit']) ? (float)$_POST['emergencyLimit'] : null;
    $isActive = isset($_POST['isActive']) ? 1 : 0;
    $effectiveDate = $_POST['effectiveDate'];
    $endDate = !empty($_POST['endDate']) ? $_POST['endDate'] : null;

    // Validation
    if (empty($planName)) {
        http_response_code(400);
        echo json_encode(['error' => 'Plan name is required.']);
        exit;
    }

    if ($providerId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid provider is required.']);
        exit;
    }

    if ($monthlyPremium <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Monthly premium must be greater than 0.']);
        exit;
    }

    if ($planId) {
        // Update existing plan
        $sql = "UPDATE HMOPlans SET
                    ProviderID = :providerId,
                    PlanName = :planName,
                    Description = :description,
                    CoverageType = :coverageType,
                    MonthlyPremium = :monthlyPremium,
                    AnnualLimit = :annualLimit,
                    RoomAndBoardLimit = :roomAndBoardLimit,
                    DoctorVisitLimit = :doctorVisitLimit,
                    EmergencyLimit = :emergencyLimit,
                    IsActive = :isActive,
                    EffectiveDate = :effectiveDate,
                    EndDate = :endDate,
                    UpdatedAt = CURRENT_TIMESTAMP
                WHERE PlanID = :planId";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':planId', $planId, PDO::PARAM_INT);
    } else {
        // Insert new plan
        $sql = "INSERT INTO HMOPlans
                (ProviderID, PlanName, Description, CoverageType, MonthlyPremium, AnnualLimit,
                 RoomAndBoardLimit, DoctorVisitLimit, EmergencyLimit, IsActive, EffectiveDate, EndDate, CreatedAt)
                VALUES (:providerId, :planName, :description, :coverageType, :monthlyPremium, :annualLimit,
                        :roomAndBoardLimit, :doctorVisitLimit, :emergencyLimit, :isActive, :effectiveDate, :endDate, CURRENT_TIMESTAMP)";

        $stmt = $pdo->prepare($sql);
    }

    $stmt->bindParam(':providerId', $providerId, PDO::PARAM_INT);
    $stmt->bindParam(':planName', $planName);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':coverageType', $coverageType);
    $stmt->bindParam(':monthlyPremium', $monthlyPremium, PDO::PARAM_STR);
    $stmt->bindParam(':annualLimit', $annualLimit, PDO::PARAM_STR);
    $stmt->bindParam(':roomAndBoardLimit', $roomAndBoardLimit, PDO::PARAM_STR);
    $stmt->bindParam(':doctorVisitLimit', $doctorVisitLimit, PDO::PARAM_STR);
    $stmt->bindParam(':emergencyLimit', $emergencyLimit, PDO::PARAM_STR);
    $stmt->bindParam(':isActive', $isActive, PDO::PARAM_INT);
    $stmt->bindParam(':effectiveDate', $effectiveDate);
    $stmt->bindParam(':endDate', $endDate);

    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => $planId ? 'Plan updated successfully.' : 'Plan created successfully.',
        'planId' => $planId ?: $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    error_log("Save HMO Plan Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while saving plan.']);
} catch (Exception $e) {
    error_log("Save HMO Plan Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
exit;
?>

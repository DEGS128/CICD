<?php
/**
 * API Endpoint: Add HMO Plan
 * Creates a new HMO plan
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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
    // Get form data
    $providerId = filter_var($_POST['providerId'] ?? 0, FILTER_VALIDATE_INT);
    $planName = trim($_POST['planName'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $coverageType = trim($_POST['coverageType'] ?? '');
    $monthlyPremium = filter_var($_POST['monthlyPremium'] ?? 0, FILTER_VALIDATE_FLOAT);
    $annualLimit = filter_var($_POST['annualLimit'] ?? 0, FILTER_VALIDATE_FLOAT);
    $roomAndBoardLimit = filter_var($_POST['roomAndBoardLimit'] ?? 0, FILTER_VALIDATE_FLOAT);
    $doctorVisitLimit = filter_var($_POST['doctorVisitLimit'] ?? 0, FILTER_VALIDATE_FLOAT);
    $emergencyLimit = filter_var($_POST['emergencyLimit'] ?? 0, FILTER_VALIDATE_FLOAT);
    $effectiveDate = $_POST['effectiveDate'] ?? date('Y-m-d');

    // Validate required fields
    if (empty($planName) || $providerId <= 0) {
        echo json_encode(['error' => 'Plan name and provider are required.']);
        exit;
    }

    // Check if plan already exists for this provider
    $checkSql = "SELECT PlanID FROM HMOPlans WHERE PlanName = :planName AND ProviderID = :providerId";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->bindParam(':planName', $planName);
    $checkStmt->bindParam(':providerId', $providerId);
    $checkStmt->execute();
    
    if ($checkStmt->fetch()) {
        echo json_encode(['error' => 'Plan with this name already exists for this provider.']);
        exit;
    }

    // Insert new plan
    $sql = "INSERT INTO HMOPlans (ProviderID, PlanName, Description, CoverageType, MonthlyPremium, AnnualLimit, RoomAndBoardLimit, DoctorVisitLimit, EmergencyLimit, EffectiveDate) 
            VALUES (:providerId, :planName, :description, :coverageType, :monthlyPremium, :annualLimit, :roomAndBoardLimit, :doctorVisitLimit, :emergencyLimit, :effectiveDate)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':providerId', $providerId);
    $stmt->bindParam(':planName', $planName);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':coverageType', $coverageType);
    $stmt->bindParam(':monthlyPremium', $monthlyPremium);
    $stmt->bindParam(':annualLimit', $annualLimit);
    $stmt->bindParam(':roomAndBoardLimit', $roomAndBoardLimit);
    $stmt->bindParam(':doctorVisitLimit', $doctorVisitLimit);
    $stmt->bindParam(':emergencyLimit', $emergencyLimit);
    $stmt->bindParam(':effectiveDate', $effectiveDate);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'HMO Plan added successfully.',
            'planId' => $pdo->lastInsertId()
        ]);
    } else {
        echo json_encode(['error' => 'Failed to add HMO Plan.']);
    }

} catch (PDOException $e) {
    error_log("Add HMO Plan Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while adding HMO Plan.']);
} catch (Exception $e) {
    error_log("Add HMO Plan Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
exit;
?>

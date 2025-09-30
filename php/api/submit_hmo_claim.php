<?php
/**
 * API Endpoint: Submit HMO Claim
 * Submits a new HMO claim
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
    $enrollmentId = filter_var($_POST['enrollmentId'] ?? 0, FILTER_VALIDATE_INT);
    $dependentId = filter_var($_POST['dependentId'] ?? 0, FILTER_VALIDATE_INT);
    $claimDate = $_POST['claimDate'] ?? date('Y-m-d');
    $serviceDate = $_POST['serviceDate'] ?? date('Y-m-d');
    $providerName = trim($_POST['providerName'] ?? '');
    $serviceType = trim($_POST['serviceType'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $treatmentDescription = trim($_POST['treatmentDescription'] ?? '');
    $amountClaimed = filter_var($_POST['amountClaimed'] ?? 0, FILTER_VALIDATE_FLOAT);
    $notes = trim($_POST['notes'] ?? '');

    // Validate required fields
    if ($enrollmentId <= 0 || empty($providerName) || empty($serviceType) || $amountClaimed <= 0) {
        echo json_encode(['error' => 'Enrollment, provider name, service type, and amount are required.']);
        exit;
    }

    // Generate unique claim number
    $claimNumber = 'HMO-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

    // Insert new claim
    $sql = "INSERT INTO HMOClaims (EnrollmentID, DependentID, ClaimNumber, ClaimDate, ServiceDate, ProviderName, ServiceType, Diagnosis, TreatmentDescription, AmountClaimed, Notes) 
            VALUES (:enrollmentId, :dependentId, :claimNumber, :claimDate, :serviceDate, :providerName, :serviceType, :diagnosis, :treatmentDescription, :amountClaimed, :notes)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':enrollmentId', $enrollmentId);
    $stmt->bindParam(':dependentId', $dependentId ?: null);
    $stmt->bindParam(':claimNumber', $claimNumber);
    $stmt->bindParam(':claimDate', $claimDate);
    $stmt->bindParam(':serviceDate', $serviceDate);
    $stmt->bindParam(':providerName', $providerName);
    $stmt->bindParam(':serviceType', $serviceType);
    $stmt->bindParam(':diagnosis', $diagnosis);
    $stmt->bindParam(':treatmentDescription', $treatmentDescription);
    $stmt->bindParam(':amountClaimed', $amountClaimed);
    $stmt->bindParam(':notes', $notes);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'HMO claim submitted successfully.',
            'claimId' => $pdo->lastInsertId(),
            'claimNumber' => $claimNumber
        ]);
    } else {
        echo json_encode(['error' => 'Failed to submit HMO claim.']);
    }

} catch (PDOException $e) {
    error_log("Submit HMO Claim Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while submitting HMO claim.']);
} catch (Exception $e) {
    error_log("Submit HMO Claim Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
exit;
?>

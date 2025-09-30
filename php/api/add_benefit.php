<?php
/**
 * API Endpoint: Add Benefit
 * Creates a new benefit
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
    $categoryId = filter_var($_POST['categoryId'] ?? 0, FILTER_VALIDATE_INT);
    $benefitName = trim($_POST['benefitName'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $benefitType = trim($_POST['benefitType'] ?? '');
    $value = filter_var($_POST['value'] ?? 0, FILTER_VALIDATE_FLOAT);
    $currency = trim($_POST['currency'] ?? 'PHP');
    $isTaxable = isset($_POST['isTaxable']) ? (bool)$_POST['isTaxable'] : true;
    $effectiveDate = $_POST['effectiveDate'] ?? date('Y-m-d');

    // Validate required fields
    if (empty($benefitName) || $categoryId <= 0) {
        echo json_encode(['error' => 'Benefit name and category are required.']);
        exit;
    }

    // Check if benefit already exists
    $checkSql = "SELECT BenefitID FROM Benefits WHERE BenefitName = :benefitName";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->bindParam(':benefitName', $benefitName);
    $checkStmt->execute();
    
    if ($checkStmt->fetch()) {
        echo json_encode(['error' => 'Benefit with this name already exists.']);
        exit;
    }

    // Insert new benefit
    $sql = "INSERT INTO Benefits (CategoryID, BenefitName, Description, BenefitType, Value, Currency, IsTaxable, EffectiveDate) 
            VALUES (:categoryId, :benefitName, :description, :benefitType, :value, :currency, :isTaxable, :effectiveDate)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':categoryId', $categoryId);
    $stmt->bindParam(':benefitName', $benefitName);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':benefitType', $benefitType);
    $stmt->bindParam(':value', $value);
    $stmt->bindParam(':currency', $currency);
    $stmt->bindParam(':isTaxable', $isTaxable, PDO::PARAM_BOOL);
    $stmt->bindParam(':effectiveDate', $effectiveDate);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Benefit added successfully.',
            'benefitId' => $pdo->lastInsertId()
        ]);
    } else {
        echo json_encode(['error' => 'Failed to add Benefit.']);
    }

} catch (PDOException $e) {
    error_log("Add Benefit Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while adding Benefit.']);
} catch (Exception $e) {
    error_log("Add Benefit Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
exit;
?>

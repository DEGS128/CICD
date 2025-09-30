<?php
/**
 * API Endpoint: Add Benefits Category
 * Creates a new benefits category
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
    $categoryName = trim($_POST['categoryName'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Validate required fields
    if (empty($categoryName)) {
        echo json_encode(['error' => 'Category name is required.']);
        exit;
    }

    // Check if category already exists
    $checkSql = "SELECT CategoryID FROM BenefitsCategories WHERE CategoryName = :categoryName";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->bindParam(':categoryName', $categoryName);
    $checkStmt->execute();
    
    if ($checkStmt->fetch()) {
        echo json_encode(['error' => 'Category with this name already exists.']);
        exit;
    }

    // Insert new category
    $sql = "INSERT INTO BenefitsCategories (CategoryName, Description) 
            VALUES (:categoryName, :description)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':categoryName', $categoryName);
    $stmt->bindParam(':description', $description);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Benefits Category added successfully.',
            'categoryId' => $pdo->lastInsertId()
        ]);
    } else {
        echo json_encode(['error' => 'Failed to add Benefits Category.']);
    }

} catch (PDOException $e) {
    error_log("Add Benefits Category Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while adding Benefits Category.']);
} catch (Exception $e) {
    error_log("Add Benefits Category Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
exit;
?>

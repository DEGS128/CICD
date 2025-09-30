<?php
/**
 * API Endpoint: Get Benefits
 * Retrieves all benefits with category information
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
                b.BenefitID,
                b.CategoryID,
                b.BenefitName,
                b.Description,
                b.BenefitType,
                b.Value,
                b.Currency,
                b.IsTaxable,
                b.IsActive,
                b.EffectiveDate,
                b.EndDate,
                b.CreatedAt,
                b.UpdatedAt,
                bc.CategoryName
            FROM Benefits b
            LEFT JOIN BenefitsCategories bc ON b.CategoryID = bc.CategoryID
            ORDER BY bc.CategoryName ASC, b.BenefitName ASC";
    
    $stmt = $pdo->query($sql);
    $benefits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'benefits' => $benefits
    ]);

} catch (PDOException $e) {
    error_log("Get Benefits Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while fetching benefits.']);
} catch (Exception $e) {
    error_log("Get Benefits Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
exit;
?>

<?php
/**
 * API Endpoint: Get HMO Providers
 * Retrieves all HMO providers with their details
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
                ProviderID,
                ProviderName,
                ContactPerson,
                ContactEmail,
                ContactPhone,
                Address,
                IsActive,
                CreatedAt,
                UpdatedAt
            FROM HMOProviders 
            ORDER BY ProviderName ASC";
    
    $stmt = $pdo->query($sql);
    $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'providers' => $providers
    ]);

} catch (PDOException $e) {
    error_log("Get HMO Providers Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred while fetching HMO providers.']);
} catch (Exception $e) {
    error_log("Get HMO Providers Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
exit;
?>

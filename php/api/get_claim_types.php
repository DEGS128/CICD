<?php
// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production

// --- Database Connection ---
try {
    require_once '../db_connect.php';
} catch (Throwable $e) {
    error_log("Failed to include db_connect.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error.']);
    exit;
}

try {
    // Fetch only necessary fields for the dropdown
    $sql = "SELECT ClaimTypeID, TypeName, RequiresReceipt FROM ClaimTypes ORDER BY TypeName";
    $stmt = $pdo->query($sql);
    $claimTypes = $stmt->fetchAll();

    echo json_encode($claimTypes);

} catch (\PDOException $e) {
    error_log("API Error (get_claim_types): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve claim types.']);
}
?>

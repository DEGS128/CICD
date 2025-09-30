<?php
// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
header('Access-Control-Allow-Methods: POST, OPTIONS, DELETE'); // Allow POST/DELETE
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Database Connection ---
try {
    require_once '../db_connect.php';
} catch (Throwable $e) {
    error_log("Failed to include db_connect.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error.']);
    exit;
}

// Check if it's a POST or DELETE request (Using POST for simplicity from JS)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST method required for delete.']);
    exit;
}

// --- Get Data from Request Body (expecting JSON) ---
$input_data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload received.']);
    exit;
}

// Extract and validate ID
$claim_type_id = isset($input_data['claim_type_id']) ? filter_var($input_data['claim_type_id'], FILTER_VALIDATE_INT) : null;

if (empty($claim_type_id) || $claim_type_id === false || $claim_type_id <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Valid Claim Type ID is required.']);
    exit;
}
// --- End Validation ---

// --- Authorization Check (Placeholder) ---
$can_manage_types = true; // Replace with real check
if (!$can_manage_types) {
     http_response_code(403); // Forbidden
     echo json_encode(['error' => 'You do not have permission to manage claim types.']);
     exit;
}
// --- End Authorization Check ---

// --- Delete from Database ---
try {
    // IMPORTANT: Check if this claim type is in use in the Claims table.
    // Deleting it could cause issues if claims reference it.
    // Consider soft delete (adding an IsActive column) instead of hard delete.
    $checkSql = "SELECT COUNT(*) FROM Claims WHERE ClaimTypeID = :claim_type_id";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->bindParam(':claim_type_id', $claim_type_id, PDO::PARAM_INT);
    $checkStmt->execute();
    $usageCount = $checkStmt->fetchColumn();

    if ($usageCount > 0) {
         http_response_code(409); // Conflict
         echo json_encode(['error' => 'Cannot delete claim type because it is currently associated with existing claims.']);
         exit;
    }

    // Proceed with deletion if not in use
    $sql = "DELETE FROM ClaimTypes WHERE ClaimTypeID = :claim_type_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':claim_type_id', $claim_type_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        // Success response
        http_response_code(200); // OK
        echo json_encode(['message' => 'Claim type deleted successfully.']);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Claim type not found with the specified ID.']);
    }

} catch (\PDOException $e) {
    error_log("API Error (delete_claim_type - DB Delete): " . $e->getMessage());
    // Check for foreign key constraint errors if deletion is blocked by DB
    if ($e->getCode() == '23000') {
         http_response_code(409); // Conflict
         echo json_encode(['error' => 'Cannot delete claim type. It might be in use.']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete claim type from database.']);
    }
}
// --- End Delete from Database ---
?>

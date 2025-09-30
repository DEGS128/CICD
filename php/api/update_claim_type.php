<?php
// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
header('Access-Control-Allow-Methods: POST, OPTIONS'); // Allow POST for update simplicity
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

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST method required for update.']);
    exit;
}

// --- Get Data from POST Request (expecting JSON) ---
$input_data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload received.']);
    exit;
}

// --- Extract and sanitize data ---
$claim_type_id = isset($input_data['claim_type_id']) ? filter_var($input_data['claim_type_id'], FILTER_VALIDATE_INT) : null;
$type_name = isset($input_data['type_name']) ? trim(htmlspecialchars($input_data['type_name'])) : null;
$description = isset($input_data['description']) ? trim(htmlspecialchars($input_data['description'])) : null;
// Use FILTER_VALIDATE_INT and check for 0 or 1, as JS sends 0/1
$requires_receipt_input = isset($input_data['requires_receipt']) ? filter_var($input_data['requires_receipt'], FILTER_VALIDATE_INT) : null;

// --- Validate Input ---
$errors = [];
if (empty($claim_type_id) || $claim_type_id === false || $claim_type_id <= 0) {
    $errors['claim_type_id'] = 'Valid Claim Type ID is required for update.'; // This ID is needed for WHERE clause
}
if (empty($type_name)) {
    $errors['type_name'] = 'Claim Type Name is required.';
}
// Validate requires_receipt is 0 or 1
if ($requires_receipt_input === null || ($requires_receipt_input !== 0 && $requires_receipt_input !== 1)) {
     $errors['requires_receipt'] = 'Requires Receipt must be a valid value (0 or 1).';
     $requires_receipt_db = 0; // Default to 0 if invalid
} else {
    $requires_receipt_db = $requires_receipt_input; // Use the validated 0 or 1
}
if (empty($description)) $description = null; // Allow empty description

// *** REMOVED incorrect validation for claim_id, status, approver_id ***

if (!empty($errors)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
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


// --- Update Database ---
try {
    $sql = "UPDATE ClaimTypes
            SET TypeName = :type_name,
                Description = :description,
                RequiresReceipt = :requires_receipt
            WHERE ClaimTypeID = :claim_type_id"; // Use the ID in the WHERE clause
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':type_name', $type_name, PDO::PARAM_STR);
    $stmt->bindParam(':description', $description, PDO::PARAM_STR);
    $stmt->bindParam(':requires_receipt', $requires_receipt_db, PDO::PARAM_INT); // Bind the 0 or 1
    $stmt->bindParam(':claim_type_id', $claim_type_id, PDO::PARAM_INT); // Bind the ID for the WHERE clause

    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        // Success response - row was updated
        http_response_code(200); // OK
        echo json_encode([
            'message' => 'Claim type updated successfully.',
            'claim_type_id' => $claim_type_id
        ]);
    } else {
        // Check if the ID existed but nothing changed, or if ID didn't exist
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM ClaimTypes WHERE ClaimTypeID = :id");
        $checkStmt->bindParam(':id', $claim_type_id, PDO::PARAM_INT);
        $checkStmt->execute();
        if ($checkStmt->fetchColumn() > 0) {
             // ID exists, but maybe no data actually changed
             http_response_code(200); // OK
             echo json_encode(['message' => 'Claim type details submitted, no changes detected.', 'claim_type_id' => $claim_type_id]);
        } else {
             // The ID provided for update was not found
             http_response_code(404); // Not Found
             echo json_encode(['error' => 'Claim type not found with the specified ID.']);
        }
    }


} catch (\PDOException $e) {
    error_log("API Error (update_claim_type - DB Update): " . $e->getMessage());
    if ($e->getCode() == '23000') { // Integrity constraint violation (e.g., duplicate TypeName on update)
         http_response_code(409); // Conflict
         echo json_encode(['error' => 'Failed to update claim type. A type with this name might already exist.']);
    } else {
         http_response_code(500);
         echo json_encode(['error' => 'Failed to update claim type details in database.']);
    }
}
// --- End Update Database ---
?>

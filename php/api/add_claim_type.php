<?php
/**
 * API Endpoint: Add Claim Type
 * Allows authorized users to add a new claim type.
 * v2.0 - Added Authentication & Authorization checks.
 */

// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log');

// IMPORTANT: Session must be started BEFORE any output
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true'); // Needed for sessions

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Database Connection ---
$pdo = null;
try {
    require_once '../db_connect.php';
    if (!isset($pdo) || !$pdo instanceof PDO) {
         throw new Exception('$pdo object not created by db_connect.php');
    }
} catch (Throwable $e) {
    error_log("CRITICAL PHP Error: Failed to include or connect via db_connect.php in add_claim_type.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: Cannot connect to database.']);
    exit;
}

// --- Authentication & Authorization Check ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Authentication required. Please log in.']);
    exit;
}

// Define roles allowed to perform this action (e.g., System Admin=1, HR Admin=2)
$allowed_roles = [1, 2];
if (!isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], $allowed_roles)) {
     http_response_code(403); // Forbidden
     echo json_encode(['error' => 'Permission denied. You do not have rights to manage claim types.']);
     exit;
}
// --- End Auth Check ---

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST method required.']);
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
$type_name = isset($input_data['type_name']) ? trim(htmlspecialchars($input_data['type_name'])) : null;
$description = isset($input_data['description']) ? trim(htmlspecialchars($input_data['description'])) : null;
$requires_receipt_input = isset($input_data['requires_receipt']) ? filter_var($input_data['requires_receipt'], FILTER_VALIDATE_INT) : null;

// --- Validate Input ---
$errors = [];
if (empty($type_name)) {
    $errors['type_name'] = 'Claim Type Name is required.';
}
if ($requires_receipt_input === null || ($requires_receipt_input !== 0 && $requires_receipt_input !== 1)) {
     $errors['requires_receipt'] = 'Requires Receipt must be a valid value (0 or 1).';
     $requires_receipt_db = 0; // Default to 0 if invalid
} else {
    $requires_receipt_db = $requires_receipt_input;
}
if (empty($description)) $description = null;

if (!empty($errors)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}
// --- End Validation ---

// --- Insert into Database ---
try {
    $sql = "INSERT INTO ClaimTypes (TypeName, Description, RequiresReceipt)
            VALUES (:type_name, :description, :requires_receipt)";
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':type_name', $type_name, PDO::PARAM_STR);
    $stmt->bindParam(':description', $description, PDO::PARAM_STR);
    $stmt->bindParam(':requires_receipt', $requires_receipt_db, PDO::PARAM_INT);

    $stmt->execute();
    $new_claim_type_id = $pdo->lastInsertId();

    // Success response
    http_response_code(201); // Created
    echo json_encode([
        'message' => 'Claim type added successfully.',
        'claim_type_id' => $new_claim_type_id
    ]);

} catch (\PDOException $e) {
    error_log("API Error (add_claim_type - DB Insert): " . $e->getMessage());
    if ($e->getCode() == '23000') { // Integrity constraint violation (e.g., duplicate TypeName)
         http_response_code(409); // Conflict
         echo json_encode(['error' => 'Failed to add claim type. A type with this name might already exist.']);
    } else {
         http_response_code(500);
         echo json_encode(['error' => 'Failed to save claim type details to database.']);
    }
} catch (Throwable $e) {
     error_log("API Error (add_claim_type - General): " . $e->getMessage());
     http_response_code(500);
     echo json_encode(['error' => 'An unexpected server error occurred.']);
}
// --- End Insert into Database ---

exit;
?>

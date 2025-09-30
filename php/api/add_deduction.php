<?php
// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

// Extract and sanitize data
$employee_id = isset($input_data['employee_id']) ? filter_var($input_data['employee_id'], FILTER_VALIDATE_INT) : null;
$payroll_id = isset($input_data['payroll_id']) ? filter_var($input_data['payroll_id'], FILTER_VALIDATE_INT) : null;
$deduction_type = isset($input_data['deduction_type']) ? trim(htmlspecialchars($input_data['deduction_type'])) : null;
$deduction_amount = isset($input_data['deduction_amount']) ? filter_var($input_data['deduction_amount'], FILTER_VALIDATE_FLOAT) : null;
$provider = isset($input_data['provider']) ? trim(htmlspecialchars($input_data['provider'])) : null;


// --- Validate Input ---
$errors = [];
if (empty($employee_id) || $employee_id === false || $employee_id <= 0) {
    $errors['employee_id'] = 'Valid Employee ID is required.';
}
if (empty($payroll_id) || $payroll_id === false || $payroll_id <= 0) {
    $errors['payroll_id'] = 'Valid Payroll Run ID is required.';
}
if (empty($deduction_type)) {
    $errors['deduction_type'] = 'Deduction Type is required.';
}
if ($deduction_amount === null || $deduction_amount === false || $deduction_amount <= 0) {
    $errors['deduction_amount'] = 'Valid Deduction Amount (positive number) is required.';
}
if (empty($provider)) $provider = null; // Set to NULL if empty


if (!empty($errors)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}
// --- End Validation ---


// --- Insert into Database ---
try {
    // Use the correct table name from your SQL schema
    $sql = "INSERT INTO Deductions (EmployeeID, PayrollID, DeductionType, DeductionAmount, Provider)
            VALUES (:employee_id, :payroll_id, :deduction_type, :deduction_amount, :provider)";
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
    $stmt->bindParam(':payroll_id', $payroll_id, PDO::PARAM_INT);
    $stmt->bindParam(':deduction_type', $deduction_type, PDO::PARAM_STR);
    $stmt->bindParam(':deduction_amount', $deduction_amount); // PDO determines type
    $stmt->bindParam(':provider', $provider, PDO::PARAM_STR);


    $stmt->execute();
    $new_deduction_id = $pdo->lastInsertId();

    // Success response
    http_response_code(201); // Created
    echo json_encode([
        'message' => 'Deduction added successfully.',
        'deduction_id' => $new_deduction_id
    ]);

} catch (\PDOException $e) {
    error_log("API Error (add_deduction - DB Insert): " . $e->getMessage());
    if ($e->getCode() == '23000') { // Integrity constraint violation (e.g., invalid EmployeeID/PayrollID)
         http_response_code(400);
         echo json_encode(['error' => 'Failed to add deduction. Ensure Employee and Payroll Run IDs are valid.']);
    } else {
         http_response_code(500);
         echo json_encode(['error' => 'Failed to save deduction details to database.']);
    }
}
// --- End Insert into Database ---
?>

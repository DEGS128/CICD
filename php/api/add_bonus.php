<?php
// --- Error Reporting for Debugging ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log');
// --- End Error Reporting ---

// --- Set Headers EARLY ---
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
// --- End Headers ---

// --- Database Connection ---
try {
    require_once '../db_connect.php';
} catch (Throwable $e) {
    error_log("Failed to include db_connect.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: Cannot connect to database.']);
    exit;
}
// --- End Database Connection ---

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
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
$bonus_amount = isset($input_data['bonus_amount']) ? filter_var($input_data['bonus_amount'], FILTER_VALIDATE_FLOAT) : null;
$bonus_type = isset($input_data['bonus_type']) ? trim(htmlspecialchars($input_data['bonus_type'])) : null;
$award_date = isset($input_data['award_date']) ? $input_data['award_date'] : null; // Expect YYYY-MM-DD
$payment_date = isset($input_data['payment_date']) ? $input_data['payment_date'] : null; // Expect YYYY-MM-DD, optional
$payroll_id = isset($input_data['payroll_id']) ? filter_var($input_data['payroll_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) : null; // Optional

// --- Validate Input ---
$errors = [];
if (empty($employee_id) || $employee_id === false || $employee_id <= 0) {
    $errors['employee_id'] = 'Valid Employee ID is required.';
}
if ($bonus_amount === null || $bonus_amount === false || $bonus_amount <= 0) { // Bonus should be positive
    $errors['bonus_amount'] = 'Valid Bonus Amount (positive number) is required.';
}
if (empty($award_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $award_date)) {
    $errors['award_date'] = 'Valid Award Date (YYYY-MM-DD) is required.';
}
// Validate payment_date only if provided
if (!empty($payment_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_date)) {
     $errors['payment_date'] = 'Payment Date must be in YYYY-MM-DD format if provided.';
} elseif (!empty($payment_date) && $payment_date < $award_date) {
     $errors['payment_date'] = 'Payment Date cannot be before Award Date.';
}
// Validate payroll_id only if provided
if (isset($input_data['payroll_id']) && $input_data['payroll_id'] !== '' && ($payroll_id === false || $payroll_id <= 0)) {
     $errors['payroll_id'] = 'Invalid Payroll Run ID provided.';
     $payroll_id = null; // Treat invalid as null
}

// Set optional fields to NULL if empty
if (empty($payment_date)) $payment_date = null;
if (empty($payroll_id)) $payroll_id = null;
if (empty($bonus_type)) $bonus_type = null;


if (!empty($errors)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}
// --- End Validation ---


// --- Insert into Database ---
try {
    $sql = "INSERT INTO Bonuses (EmployeeID, PayrollID, BonusAmount, BonusType, AwardDate, PaymentDate)
            VALUES (:employee_id, :payroll_id, :bonus_amount, :bonus_type, :award_date, :payment_date)";
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
    $stmt->bindValue(':payroll_id', $payroll_id, $payroll_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindParam(':bonus_amount', $bonus_amount); // PDO determines type
    $stmt->bindParam(':bonus_type', $bonus_type, PDO::PARAM_STR);
    $stmt->bindParam(':award_date', $award_date, PDO::PARAM_STR);
    $stmt->bindValue(':payment_date', $payment_date, $payment_date === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

    $stmt->execute();
    $new_bonus_id = $pdo->lastInsertId();

    // Success response
    http_response_code(201); // Created
    echo json_encode([
        'message' => 'Bonus added successfully.',
        'bonus_id' => $new_bonus_id
    ]);

} catch (\PDOException $e) {
    error_log("API Error (add_bonus - DB Insert): " . $e->getMessage() . " SQL: " . $sql);
    if ($e->getCode() == '23000') { // Integrity constraint violation (e.g., invalid EmployeeID/PayrollID)
         http_response_code(400);
         echo json_encode(['error' => 'Failed to add bonus. Ensure Employee and Payroll Run IDs are valid.']);
    } else {
         http_response_code(500);
         echo json_encode(['error' => 'Failed to save bonus details to database.']);
    }
}
// --- End Insert into Database ---
?>

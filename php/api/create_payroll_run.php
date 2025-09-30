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
$start_date = isset($input_data['pay_period_start_date']) ? $input_data['pay_period_start_date'] : null; // Expect YYYY-MM-DD
$end_date = isset($input_data['pay_period_end_date']) ? $input_data['pay_period_end_date'] : null;     // Expect YYYY-MM-DD
$payment_date = isset($input_data['payment_date']) ? $input_data['payment_date'] : null;   // Expect YYYY-MM-DD

// --- Validate Input ---
$errors = [];
if (empty($start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    $errors['pay_period_start_date'] = 'Valid Pay Period Start Date (YYYY-MM-DD) is required.';
}
if (empty($end_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    $errors['pay_period_end_date'] = 'Valid Pay Period End Date (YYYY-MM-DD) is required.';
} elseif (!empty($start_date) && $end_date < $start_date) {
     $errors['pay_period_end_date'] = 'Pay Period End Date cannot be before Start Date.';
}
if (empty($payment_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_date)) {
    $errors['payment_date'] = 'Valid Payment Date (YYYY-MM-DD) is required.';
} elseif (!empty($end_date) && $payment_date < $end_date) {
     $errors['payment_date'] = 'Payment Date cannot be before Pay Period End Date.';
}

// Optional: Check for overlapping payroll periods
// $checkSql = "SELECT PayrollID FROM PayrollRuns WHERE ...";

if (!empty($errors)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}
// --- End Validation ---


// --- Insert into Database ---
try {
    $initial_status = 'Pending'; // Default status for a new run

    // Use the correct table name from your SQL schema
    $sql = "INSERT INTO PayrollRuns (PayPeriodStartDate, PayPeriodEndDate, PaymentDate, Status)
            VALUES (:start_date, :end_date, :payment_date, :status)";
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
    $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    $stmt->bindParam(':payment_date', $payment_date, PDO::PARAM_STR);
    $stmt->bindParam(':status', $initial_status, PDO::PARAM_STR);


    $stmt->execute();
    $new_payroll_id = $pdo->lastInsertId();

    // Success response
    http_response_code(201); // Created
    echo json_encode([
        'message' => 'Payroll run created successfully.',
        'payroll_id' => $new_payroll_id
    ]);

} catch (\PDOException $e) {
    error_log("API Error (create_payroll_run - DB Insert): " . $e->getMessage());
    // Check for specific errors like unique constraint violations if needed
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create payroll run in database.']);
}
// --- End Insert into Database ---
?>

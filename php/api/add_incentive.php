<?php
// --- Error Reporting & Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log');

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
$pdo = null;
try {
    require_once '../db_connect.php';
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('DB connection failed');
    }
} catch (Throwable $e) {
    error_log("PHP Error in add_incentive.php (db_connect include): " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Server configuration error.']);
    exit;
}

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

// --- Extract and sanitize data ---
$employee_id = isset($input_data['employee_id']) ? filter_var($input_data['employee_id'], FILTER_VALIDATE_INT) : null;
$plan_id = isset($input_data['plan_id']) ? filter_var($input_data['plan_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) : null; // Optional
$incentive_type = isset($input_data['incentive_type']) ? trim(htmlspecialchars($input_data['incentive_type'])) : null;
$amount = isset($input_data['amount']) ? filter_var($input_data['amount'], FILTER_VALIDATE_FLOAT) : null;
$award_date = isset($input_data['award_date']) ? $input_data['award_date'] : null; // Expect YYYY-MM-DD
$payout_date = isset($input_data['payout_date']) ? $input_data['payout_date'] : null; // Expect YYYY-MM-DD, optional
$payroll_id = isset($input_data['payroll_id']) ? filter_var($input_data['payroll_id'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) : null; // Optional

// --- Validate Input ---
$errors = [];
if (empty($employee_id) || $employee_id <= 0) {
    $errors['employee_id'] = 'Valid Employee ID is required.';
}
if ($amount === null || $amount <= 0) {
    $errors['amount'] = 'Valid Incentive Amount (positive number) is required.';
}
if (empty($award_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $award_date)) {
    $errors['award_date'] = 'Valid Award Date (YYYY-MM-DD) is required.';
}
// Validate optional fields only if provided
if (!empty($payout_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $payout_date)) {
     $errors['payout_date'] = 'Payout Date must be in YYYY-MM-DD format if provided.';
} elseif (!empty($payout_date) && $payout_date < $award_date) {
     $errors['payout_date'] = 'Payout Date cannot be before Award Date.';
}
if (isset($input_data['plan_id']) && $input_data['plan_id'] !== '' && ($plan_id === false || $plan_id <= 0)) {
     $errors['plan_id'] = 'Invalid Plan ID provided.';
     $plan_id = null;
}
if (isset($input_data['payroll_id']) && $input_data['payroll_id'] !== '' && ($payroll_id === false || $payroll_id <= 0)) {
     $errors['payroll_id'] = 'Invalid Payroll Run ID provided.';
     $payroll_id = null;
}

// Set optional fields to NULL if empty
if (empty($payout_date)) $payout_date = null;
if (empty($payroll_id)) $payroll_id = null;
if (empty($plan_id)) $plan_id = null;
if (empty($incentive_type)) $incentive_type = null;

if (!empty($errors)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}
// --- End Validation ---

// --- Insert into Database ---
try {
    $sql = "INSERT INTO Incentives (EmployeeID, PlanID, IncentiveType, Amount, AwardDate, PayoutDate, PayrollID)
            VALUES (:employee_id, :plan_id, :incentive_type, :amount, :award_date, :payout_date, :payroll_id)";
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
    $stmt->bindValue(':plan_id', $plan_id, $plan_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindParam(':incentive_type', $incentive_type, PDO::PARAM_STR);
    $stmt->bindParam(':amount', $amount);
    $stmt->bindParam(':award_date', $award_date, PDO::PARAM_STR);
    $stmt->bindValue(':payout_date', $payout_date, $payout_date === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':payroll_id', $payroll_id, $payroll_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);

    $stmt->execute();
    $new_incentive_id = $pdo->lastInsertId();

    // Success response
    http_response_code(201); // Created
    echo json_encode([
        'message' => 'Incentive added successfully.',
        'incentive_id' => $new_incentive_id
    ]);

} catch (\PDOException $e) {
    error_log("PHP PDOException in add_incentive.php: " . $e->getMessage());
    if ($e->getCode() == '23000') { // Integrity constraint violation
         http_response_code(400);
         echo json_encode(['error' => 'Failed to add incentive. Ensure Employee, Plan, and Payroll IDs are valid.']);
    } else {
         http_response_code(500);
         echo json_encode(['error' => 'Database error adding incentive.']);
    }
} catch (Throwable $e) {
    error_log("PHP Throwable in add_incentive.php: " . $e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
    echo json_encode(['error' => 'Unexpected server error adding incentive.']);
}
exit;
?>

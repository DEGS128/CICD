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
$base_salary = isset($input_data['base_salary']) ? filter_var($input_data['base_salary'], FILTER_VALIDATE_FLOAT) : null;
$pay_frequency = isset($input_data['pay_frequency']) ? trim(htmlspecialchars($input_data['pay_frequency'])) : null;
$pay_rate = isset($input_data['pay_rate']) ? filter_var($input_data['pay_rate'], FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null; // Allow NULL
$effective_date = isset($input_data['effective_date']) ? $input_data['effective_date'] : null; // Expect YYYY-MM-DD

// --- Validate Input ---
$errors = [];
if (empty($employee_id) || $employee_id === false || $employee_id <= 0) {
    $errors['employee_id'] = 'Valid Employee ID is required.';
}
if ($base_salary === null || $base_salary === false || $base_salary < 0) {
    $errors['base_salary'] = 'Valid Base Salary (non-negative number) is required.';
}
if (empty($pay_frequency)) {
    $errors['pay_frequency'] = 'Pay Frequency is required.';
}
if (empty($effective_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective_date)) {
    $errors['effective_date'] = 'Valid Effective Date (YYYY-MM-DD) is required.';
}
// Validate pay_rate only if provided
if (isset($input_data['pay_rate']) && $input_data['pay_rate'] !== '' && ($pay_rate === false || $pay_rate < 0)) {
     $errors['pay_rate'] = 'Pay Rate must be a non-negative number if provided.';
}


if (!empty($errors)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}
// --- End Validation ---


// --- Process Database Update ---
try {
    $pdo->beginTransaction(); // Start transaction

    // 1. Mark existing current salary record(s) for this employee as not current
    $sql_update_old = "UPDATE EmployeeSalaries
                       SET IsCurrent = FALSE, EndDate = :effective_date_minus_one
                       WHERE EmployeeID = :employee_id AND IsCurrent = TRUE";
    $stmt_update_old = $pdo->prepare($sql_update_old);
    // Calculate day before effective date for EndDate of old record
    $effective_date_obj = new DateTime($effective_date);
    $effective_date_obj->modify('-1 day');
    $end_date_for_old = $effective_date_obj->format('Y-m-d');

    $stmt_update_old->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
    $stmt_update_old->bindParam(':effective_date_minus_one', $end_date_for_old, PDO::PARAM_STR);
    $stmt_update_old->execute();

    // 2. Insert the new salary record as current
    $sql_insert_new = "INSERT INTO EmployeeSalaries
                       (EmployeeID, BaseSalary, PayFrequency, PayRate, EffectiveDate, IsCurrent)
                       VALUES
                       (:employee_id, :base_salary, :pay_frequency, :pay_rate, :effective_date, TRUE)";
    $stmt_insert_new = $pdo->prepare($sql_insert_new);

    $stmt_insert_new->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
    $stmt_insert_new->bindParam(':base_salary', $base_salary); // PDO determines type
    $stmt_insert_new->bindParam(':pay_frequency', $pay_frequency, PDO::PARAM_STR);
    // Bind PayRate or NULL
    if ($pay_rate !== null) {
         $stmt_insert_new->bindParam(':pay_rate', $pay_rate);
    } else {
         $stmt_insert_new->bindValue(':pay_rate', null, PDO::PARAM_NULL);
    }
    $stmt_insert_new->bindParam(':effective_date', $effective_date, PDO::PARAM_STR);

    $stmt_insert_new->execute();
    $new_salary_id = $pdo->lastInsertId();

    $pdo->commit(); // Commit transaction

    // Success response
    http_response_code(201); // Created (or 200 OK if considered an update)
    echo json_encode([
        'message' => 'Salary information updated successfully.',
        'new_salary_id' => $new_salary_id
    ]);

} catch (\PDOException $e) {
    $pdo->rollBack(); // Rollback on error
    error_log("API Error (add_update_salary - DB): " . $e->getMessage());
    if ($e->getCode() == '23000') { // Integrity constraint violation (e.g., invalid EmployeeID)
         http_response_code(400);
         echo json_encode(['error' => 'Failed to update salary. Ensure Employee ID is valid.']);
    } else {
         http_response_code(500);
         echo json_encode(['error' => 'Failed to update salary information in database.']);
    }
} catch (Throwable $e) { // Catch other errors like DateTime issues
     $pdo->rollBack(); // Rollback on error
     error_log("API Error (add_update_salary - General): " . $e->getMessage());
     http_response_code(500);
     echo json_encode(['error' => 'An unexpected error occurred while updating salary.']);
}
// --- End Process Database Update ---
?>

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
$shift_id = isset($input_data['shift_id']) ? filter_var($input_data['shift_id'], FILTER_VALIDATE_INT) : null; // Shift can be optional (null)
$start_date = isset($input_data['start_date']) ? $input_data['start_date'] : null; // Expect YYYY-MM-DD
$end_date = isset($input_data['end_date']) ? $input_data['end_date'] : null;       // Expect YYYY-MM-DD, can be empty/null
$workdays = isset($input_data['workdays']) ? trim(htmlspecialchars($input_data['workdays'])) : null; // E.g., "Mon-Fri"

// --- Validate Input ---
$errors = [];
if (empty($employee_id) || $employee_id === false || $employee_id <= 0) {
    $errors['employee_id'] = 'Valid Employee ID is required.';
}
if (empty($start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    $errors['start_date'] = 'Valid Start Date (YYYY-MM-DD) is required.';
}
// Validate end_date only if provided
if (!empty($end_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
     $errors['end_date'] = 'End Date must be in YYYY-MM-DD format if provided.';
} elseif (!empty($end_date) && $end_date < $start_date) {
     $errors['end_date'] = 'End Date cannot be before Start Date.';
}
// Validate shift_id if provided (ensure it's a positive integer)
if ($shift_id !== null && ($shift_id === false || $shift_id <= 0)) {
    $errors['shift_id'] = 'Invalid Shift ID provided.';
    $shift_id = null; // Treat invalid ID as no shift selected
}
// Make end_date NULL if it's empty
if (empty($end_date)) {
    $end_date = null;
}


if (!empty($errors)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}
// --- End Validation ---


// --- Insert into Database ---
try {
    // Note: Add checks here if needed to prevent overlapping schedules for the same employee
    // This would involve querying existing schedules before inserting.

    $sql = "INSERT INTO Schedules (EmployeeID, ShiftID, StartDate, EndDate, Workdays)
            VALUES (:employee_id, :shift_id, :start_date, :end_date, :workdays)";
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
    // Bind ShiftID as INT if not null, otherwise bind as NULL
    if ($shift_id !== null) {
        $stmt->bindParam(':shift_id', $shift_id, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':shift_id', null, PDO::PARAM_NULL);
    }
    $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
    // Bind EndDate as STR if not null, otherwise bind as NULL
    if ($end_date !== null) {
        $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    } else {
        $stmt->bindValue(':end_date', null, PDO::PARAM_NULL);
    }
    $stmt->bindParam(':workdays', $workdays, PDO::PARAM_STR);


    $stmt->execute();
    $new_schedule_id = $pdo->lastInsertId();

    // Success response
    http_response_code(201); // Created
    echo json_encode([
        'message' => 'Schedule added successfully.',
        'schedule_id' => $new_schedule_id
    ]);

} catch (\PDOException $e) {
    error_log("API Error (add_schedule - DB Insert): " . $e->getMessage() . " SQL: " . $sql);
    // Check for specific errors like foreign key violations if needed
    if ($e->getCode() == '23000') { // Integrity constraint violation (e.g., invalid EmployeeID or ShiftID)
         http_response_code(400); // Bad request because referenced ID doesn't exist
         echo json_encode(['error' => 'Failed to add schedule. Ensure Employee and Shift IDs are valid.']);
    } else {
         http_response_code(500);
         echo json_encode(['error' => 'Failed to save schedule details to database.']);
    }
}
// --- End Insert into Database ---
?>

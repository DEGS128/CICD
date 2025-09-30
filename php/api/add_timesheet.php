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
$period_start_date = isset($input_data['period_start_date']) ? $input_data['period_start_date'] : null; // Expect YYYY-MM-DD
$period_end_date = isset($input_data['period_end_date']) ? $input_data['period_end_date'] : null;     // Expect YYYY-MM-DD
$schedule_id = isset($input_data['schedule_id']) ? filter_var($input_data['schedule_id'], FILTER_VALIDATE_INT) : null; // Optional

// --- Validate Input ---
$errors = [];
if (empty($employee_id) || $employee_id === false || $employee_id <= 0) {
    $errors['employee_id'] = 'Valid Employee ID is required.';
}
if (empty($period_start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $period_start_date)) {
    $errors['period_start_date'] = 'Valid Period Start Date (YYYY-MM-DD) is required.';
}
if (empty($period_end_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $period_end_date)) {
    $errors['period_end_date'] = 'Valid Period End Date (YYYY-MM-DD) is required.';
} elseif ($period_end_date < $period_start_date) {
     $errors['period_end_date'] = 'Period End Date cannot be before Period Start Date.';
}
// Validate schedule_id only if provided
if ($schedule_id !== null && ($schedule_id === false || $schedule_id <= 0)) {
    $errors['schedule_id'] = 'Invalid Schedule ID provided.';
    $schedule_id = null; // Treat invalid as null
}

// Check for duplicate timesheet for the same employee and period
if (empty($errors)) {
    try {
        $checkSql = "SELECT TimesheetID FROM Timesheets
                     WHERE EmployeeID = :employee_id
                     AND PeriodStartDate = :start_date
                     AND PeriodEndDate = :end_date";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
        $checkStmt->bindParam(':start_date', $period_start_date, PDO::PARAM_STR);
        $checkStmt->bindParam(':end_date', $period_end_date, PDO::PARAM_STR);
        $checkStmt->execute();
        if ($checkStmt->fetch()) {
             $errors['duplicate'] = 'A timesheet for this employee and period already exists.';
        }
    } catch (\PDOException $e) {
         error_log("API Error (add_timesheet - Duplicate Check): " . $e->getMessage());
         $errors['database'] = 'Error checking for existing timesheet.'; // Generic error
    }
}


if (!empty($errors)) {
    http_response_code(400); // Bad Request or 409 Conflict for duplicate
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}
// --- End Validation ---


// --- Insert into Database ---
try {
    // Initial status is usually 'Pending' or 'Draft'
    $initial_status = 'Pending';

    $sql = "INSERT INTO Timesheets (EmployeeID, ScheduleID, PeriodStartDate, PeriodEndDate, Status)
            VALUES (:employee_id, :schedule_id, :period_start_date, :period_end_date, :status)";
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
    // Bind ScheduleID or NULL
    if ($schedule_id !== null) {
        $stmt->bindParam(':schedule_id', $schedule_id, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':schedule_id', null, PDO::PARAM_NULL);
    }
    $stmt->bindParam(':period_start_date', $period_start_date, PDO::PARAM_STR);
    $stmt->bindParam(':period_end_date', $period_end_date, PDO::PARAM_STR);
    $stmt->bindParam(':status', $initial_status, PDO::PARAM_STR);

    $stmt->execute();
    $new_timesheet_id = $pdo->lastInsertId();

    // Success response
    http_response_code(201); // Created
    echo json_encode([
        'message' => 'Timesheet created successfully.',
        'timesheet_id' => $new_timesheet_id
    ]);

} catch (\PDOException $e) {
    error_log("API Error (add_timesheet - DB Insert): " . $e->getMessage() . " SQL: " . $sql);
    if ($e->getCode() == '23000') { // Integrity constraint violation (e.g., invalid EmployeeID/ScheduleID)
         http_response_code(400);
         echo json_encode(['error' => 'Failed to add timesheet. Ensure Employee and Schedule IDs are valid.']);
    } else {
         http_response_code(500);
         echo json_encode(['error' => 'Failed to save timesheet details to database.']);
    }
}
// --- End Insert into Database ---
?>

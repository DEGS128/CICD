<?php
// --- Error Reporting for Debugging ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log'); // Optional custom log
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

// --- Get Data from POST Request ---
// Use file_get_contents for JSON payload, fallback to $_POST for form-data
$input_data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    // If not valid JSON, assume form-data
    $input_data = $_POST;
}


$shift_name = isset($input_data['shift_name']) ? trim(htmlspecialchars($input_data['shift_name'])) : null;
$start_time = isset($input_data['start_time']) ? $input_data['start_time'] : null; // Expect HH:MM format
$end_time = isset($input_data['end_time']) ? $input_data['end_time'] : null;     // Expect HH:MM format
$break_duration = isset($input_data['break_duration']) ? filter_var($input_data['break_duration'], FILTER_VALIDATE_INT) : null;

// --- Validate Input ---
$errors = [];
if (empty($shift_name)) {
    $errors['shift_name'] = 'Shift Name is required.';
}
// Basic time format validation (HH:MM)
if (empty($start_time) || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $start_time)) {
    $errors['start_time'] = 'Valid Start Time (HH:MM format) is required.';
}
if (empty($end_time) || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $end_time)) {
    $errors['end_time'] = 'Valid End Time (HH:MM format) is required.';
}
// Validate break duration (optional, allow 0 or positive integer)
if ($break_duration !== null && ($break_duration === false || $break_duration < 0)) {
     $errors['break_duration'] = 'Break Duration must be a non-negative number (minutes).';
} elseif ($break_duration === null) {
    $break_duration = 0; // Default to 0 if not provided or invalid but not negative
}


if (!empty($errors)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}
// --- End Validation ---


// --- Insert into Database ---
try {
    $sql = "INSERT INTO Shifts (ShiftName, StartTime, EndTime, BreakDurationMinutes)
            VALUES (:shift_name, :start_time, :end_time, :break_duration)";
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':shift_name', $shift_name, PDO::PARAM_STR);
    $stmt->bindParam(':start_time', $start_time, PDO::PARAM_STR); // Store as TIME type in DB
    $stmt->bindParam(':end_time', $end_time, PDO::PARAM_STR);     // Store as TIME type in DB
    $stmt->bindParam(':break_duration', $break_duration, PDO::PARAM_INT);

    $stmt->execute();
    $new_shift_id = $pdo->lastInsertId();

    // Success response
    http_response_code(201); // Created
    echo json_encode([
        'message' => 'Shift added successfully.',
        'shift_id' => $new_shift_id
    ]);

} catch (\PDOException $e) {
    error_log("API Error (add_shift - DB Insert): " . $e->getMessage() . " SQL: " . $sql);
    // Check for duplicate entry error (MySQL error code 1062)
    if ($e->getCode() == '23000') { // Integrity constraint violation
         http_response_code(409); // Conflict
         echo json_encode(['error' => 'Failed to add shift. A shift with similar details might already exist.']);
    } else {
         http_response_code(500);
         echo json_encode(['error' => 'Failed to save shift details to database.']);
    }
}
// --- End Insert into Database ---
?>

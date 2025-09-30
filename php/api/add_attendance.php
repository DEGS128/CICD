<?php
// --- Error Reporting for Debugging ---
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log');
// --- End Error Reporting ---

// Start session and check authentication
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required. Please log in.']);
    exit();
}

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
$attendance_date = isset($input_data['attendance_date']) ? $input_data['attendance_date'] : null; // Expect YYYY-MM-DD
$clock_in_time = isset($input_data['clock_in_time']) ? $input_data['clock_in_time'] : null;     // Expect HH:MM or HH:MM:SS
$clock_out_time = isset($input_data['clock_out_time']) ? $input_data['clock_out_time'] : null;   // Expect HH:MM or HH:MM:SS
$status = isset($input_data['status']) ? trim(htmlspecialchars($input_data['status'])) : null;   // e.g., Present, Late, Absent, On Leave
$notes = isset($input_data['notes']) ? trim(htmlspecialchars($input_data['notes'])) : null;

// --- Validate Input ---
$errors = [];
if (empty($employee_id) || $employee_id === false || $employee_id <= 0) {
    $errors['employee_id'] = 'Valid Employee ID is required.';
}
if (empty($attendance_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendance_date)) {
    $errors['attendance_date'] = 'Valid Attendance Date (YYYY-MM-DD) is required.';
}

// Combine date and time for database DATETIME fields, handle empty times
$clock_in_datetime = null;
if (!empty($clock_in_time) && preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:([0-5]\d))?$/', $clock_in_time)) {
    $clock_in_datetime = $attendance_date . ' ' . $clock_in_time;
     // Add seconds if missing
    if (strlen($clock_in_time) <= 5) $clock_in_datetime .= ':00';
} elseif (!empty($clock_in_time)) {
     $errors['clock_in_time'] = 'Invalid Clock In Time format (use HH:MM or HH:MM:SS).';
}

$clock_out_datetime = null;
if (!empty($clock_out_time) && preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:([0-5]\d))?$/', $clock_out_time)) {
    $clock_out_datetime = $attendance_date . ' ' . $clock_out_time;
    // Add seconds if missing
    if (strlen($clock_out_time) <= 5) $clock_out_datetime .= ':00';

    // Validate clock out is after clock in if both provided
    if ($clock_in_datetime !== null && $clock_out_datetime <= $clock_in_datetime) {
        $errors['clock_out_time'] = 'Clock Out Time must be after Clock In Time.';
    }
} elseif (!empty($clock_out_time)) {
     $errors['clock_out_time'] = 'Invalid Clock Out Time format (use HH:MM or HH:MM:SS).';
}


if (!empty($errors)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}
// --- End Validation ---


// --- Insert into Database ---
try {
    // Optional: Check if a record for this employee and date already exists to prevent duplicates?
    // $checkSql = "SELECT RecordID FROM AttendanceRecords WHERE EmployeeID = :employee_id AND AttendanceDate = :attendance_date";
    // ... execute check ... if exists, return error or handle update logic ...

    $sql = "INSERT INTO AttendanceRecords (EmployeeID, AttendanceDate, ClockInTime, ClockOutTime, Status, Notes)
            VALUES (:employee_id, :attendance_date, :clock_in_time, :clock_out_time, :status, :notes)";
    $stmt = $pdo->prepare($sql);

    $stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
    $stmt->bindParam(':attendance_date', $attendance_date, PDO::PARAM_STR);
    // Bind DATETIME values or NULL
    $stmt->bindValue(':clock_in_time', $clock_in_datetime, $clock_in_datetime === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':clock_out_time', $clock_out_datetime, $clock_out_datetime === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);

    $stmt->execute();
    $new_record_id = $pdo->lastInsertId();

    // Success response
    http_response_code(201); // Created
    echo json_encode([
        'message' => 'Attendance record added successfully.',
        'record_id' => $new_record_id
    ]);

} catch (\PDOException $e) {
    error_log("API Error (add_attendance - DB Insert): " . $e->getMessage() . " SQL: " . $sql);
    if ($e->getCode() == '23000') { // Integrity constraint violation (e.g., invalid EmployeeID)
         http_response_code(400);
         echo json_encode(['error' => 'Failed to add record. Ensure Employee ID is valid.']);
    } else {
         http_response_code(500);
         echo json_encode(['error' => 'Failed to save attendance record to database.']);
    }
}
// --- End Insert into Database ---
?>

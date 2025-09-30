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

try {
    // Prepare SQL statement to select all shifts
    $sql = "SELECT
                ShiftID,
                ShiftName,
                StartTime,
                EndTime,
                BreakDurationMinutes
            FROM
                Shifts
            ORDER BY
                StartTime, ShiftName"; // Order by start time, then name

    $stmt = $pdo->query($sql);

    // Fetch all results
    $shifts = $stmt->fetchAll();

    // Format time fields for better readability (optional, can also be done in JS)
    foreach ($shifts as &$shift) {
        if (isset($shift['StartTime'])) {
            $shift['StartTimeFormatted'] = date('h:i A', strtotime($shift['StartTime']));
        }
        if (isset($shift['EndTime'])) {
             $shift['EndTimeFormatted'] = date('h:i A', strtotime($shift['EndTime']));
        }
    }
    unset($shift); // Unset reference

    // Output the results as JSON
    echo json_encode($shifts);

} catch (\PDOException $e) {
    // Log the error
    error_log("API Error (get_shifts): " . $e->getMessage());

    // Send an error response back to the client
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Failed to retrieve shift data.']);
}
?>

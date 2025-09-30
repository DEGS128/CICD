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

// --- Optional Filters (Example: by employee_id and date range) ---
$employee_id = isset($_GET['employee_id']) ? filter_var($_GET['employee_id'], FILTER_VALIDATE_INT) : null;
$start_date_filter = isset($_GET['start_date']) ? $_GET['start_date'] : null; // Expect YYYY-MM-DD
$end_date_filter = isset($_GET['end_date']) ? $_GET['end_date'] : null;     // Expect YYYY-MM-DD
// --- End Filters ---

try {
    // Base SQL query
    $sql = "SELECT
                ar.RecordID,
                ar.EmployeeID,
                CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                ar.ClockInTime,
                ar.ClockOutTime,
                ar.AttendanceDate,
                ar.Status,
                ar.Notes
            FROM
                AttendanceRecords ar
            JOIN
                Employees e ON ar.EmployeeID = e.EmployeeID";

    $conditions = [];
    $params = [];

    // Add conditions based on filters
    if ($employee_id !== null && $employee_id > 0) {
        $conditions[] = "ar.EmployeeID = :employee_id";
        $params[':employee_id'] = $employee_id;
    }
    if (!empty($start_date_filter) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date_filter)) {
         $conditions[] = "ar.AttendanceDate >= :start_date";
         $params[':start_date'] = $start_date_filter;
    }
     if (!empty($end_date_filter) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date_filter)) {
         $conditions[] = "ar.AttendanceDate <= :end_date";
         $params[':end_date'] = $end_date_filter;
    }

    // Append WHERE clause if conditions exist
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY ar.AttendanceDate DESC, e.LastName, e.FirstName, ar.ClockInTime DESC"; // Order by date, then name, then time

    // Prepare and execute the statement
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params); // Pass parameters for binding

    // Fetch all results
    $records = $stmt->fetchAll();

    // Format date/time fields (optional)
    foreach ($records as &$record) {
         if (!empty($record['AttendanceDate'])) {
             $record['AttendanceDateFormatted'] = date('M d, Y', strtotime($record['AttendanceDate']));
        }
        if (!empty($record['ClockInTime'])) {
            $record['ClockInTimeFormatted'] = date('h:i:s A', strtotime($record['ClockInTime']));
        } else {
             $record['ClockInTimeFormatted'] = '-';
        }
         if (!empty($record['ClockOutTime'])) {
            $record['ClockOutTimeFormatted'] = date('h:i:s A', strtotime($record['ClockOutTime']));
        } else {
             $record['ClockOutTimeFormatted'] = '-';
        }
    }
    unset($record); // Unset reference


    // Output the results as JSON
    echo json_encode($records);

} catch (\PDOException $e) {
    // Log the error
    error_log("API Error (get_attendance): " . $e->getMessage());

    // Send an error response back to the client
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Failed to retrieve attendance data.']);
}
?>

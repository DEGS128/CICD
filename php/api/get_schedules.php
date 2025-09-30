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

// Optional filtering (e.g., by employee_id, date range - not implemented here yet)
// $employee_id = isset($_GET['employee_id']) ? filter_var($_GET['employee_id'], FILTER_VALIDATE_INT) : null;

try {
    // Prepare SQL statement to select schedule data
    // Join with Employees and Shifts tables
    $sql = "SELECT
                s.ScheduleID,
                s.EmployeeID,
                CONCAT(e.FirstName, ' ', e.LastName) AS EmployeeName,
                s.ShiftID,
                sh.ShiftName,
                sh.StartTime,
                sh.EndTime,
                s.StartDate,
                s.EndDate,
                s.Workdays
            FROM
                Schedules s
            JOIN
                Employees e ON s.EmployeeID = e.EmployeeID
            LEFT JOIN -- Use LEFT JOIN in case ShiftID is NULL
                Shifts sh ON s.ShiftID = sh.ShiftID
            -- Add WHERE clause here if filtering is implemented
            -- WHERE s.EmployeeID = :employee_id
            ORDER BY
                e.LastName, e.FirstName, s.StartDate DESC"; // Order by employee, then most recent schedule first

    $stmt = $pdo->prepare($sql);

    // Bind parameters here if filtering is implemented
    // if ($employee_id) {
    //     $stmt->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
    // }

    $stmt->execute();

    // Fetch all results
    $schedules = $stmt->fetchAll();

     // Format time and date fields (optional)
    foreach ($schedules as &$schedule) {
        if (!empty($schedule['StartTime'])) {
            $schedule['StartTimeFormatted'] = date('h:i A', strtotime($schedule['StartTime']));
        }
         if (!empty($schedule['EndTime'])) {
            $schedule['EndTimeFormatted'] = date('h:i A', strtotime($schedule['EndTime']));
        }
        if (!empty($schedule['StartDate'])) {
             $schedule['StartDateFormatted'] = date('M d, Y', strtotime($schedule['StartDate']));
        }
         if (!empty($schedule['EndDate'])) {
             $schedule['EndDateFormatted'] = date('M d, Y', strtotime($schedule['EndDate']));
        } else {
            $schedule['EndDateFormatted'] = 'Ongoing';
        }
    }
    unset($schedule); // Unset reference


    // Output the results as JSON
    echo json_encode($schedules);

} catch (\PDOException $e) {
    // Log the error
    error_log("API Error (get_schedules): " . $e->getMessage());

    // Send an error response back to the client
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Failed to retrieve schedule data.']);
}
?>
